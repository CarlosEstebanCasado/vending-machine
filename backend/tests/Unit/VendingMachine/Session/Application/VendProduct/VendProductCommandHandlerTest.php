<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Application\VendProduct;

use App\Tests\Unit\VendingMachine\Inventory\Domain\InventorySlotMother;
use App\VendingMachine\CoinInventory\Domain\CoinInventoryRepository;
use App\VendingMachine\CoinInventory\Domain\CoinInventorySnapshot;
use App\VendingMachine\CoinInventory\Domain\Service\ChangeAvailabilityChecker;
use App\VendingMachine\Inventory\Domain\InventorySlot;
use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Session\Application\VendProduct\VendProductCommand;
use App\VendingMachine\Session\Application\VendProduct\VendProductCommandHandler;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use DomainException;
use PHPUnit\Framework\TestCase;

final class VendProductCommandHandlerTest extends TestCase
{
    public function testItDispensesProductAndChange(): void
    {
        $sessionDocument = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
            state: VendingSessionState::Collecting->value,
            balanceCents: 125,
            insertedCoins: [100 => 1, 25 => 1],
            selectedProductId: 'product-1',
            selectedSlotCode: '11',
            changePlan: null,
        );

        $slotDocument = new SlotProjectionDocument(
            machineId: 'machine-1',
            slotCode: '11',
            capacity: 10,
            recommendedSlotQuantity: 6,
            quantity: 2,
            status: 'available',
            lowStock: false,
            productId: 'product-1',
            productName: 'Soda',
            priceCents: 100,
        );

        $coinInventoryDocument = new CoinInventoryProjectionDocument(
            'machine-1',
            [25 => 2, 10 => 5, 5 => 5],
            [],
            false,
        );

        $repository = $this->createMock(DocumentRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with([
                'machineId' => 'machine-1',
                'slotCode' => '11',
            ])
            ->willReturn($slotDocument);

        $coinInventoryRepository = $this->createMock(CoinInventoryRepository::class);
        $coinInventoryRepository->expects(self::once())
            ->method('find')
            ->with('machine-1')
            ->willReturn(new CoinInventorySnapshot('machine-1', [25 => 2, 10 => 5, 5 => 5], [], false, new DateTimeImmutable('-1 minute')));
        $coinInventoryRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (CoinInventorySnapshot $snapshot): bool {
                $available = $snapshot->available;

                return 'machine-1' === $snapshot->machineId
                    && 1 === $available[100]
                    && 2 === $available[25]
                    && 5 === $available[10]
                    && 5 === $available[5]
                    && [] === $snapshot->reserved
                    && false === $snapshot->insufficientChange;
            }));

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::exactly(2))
            ->method('find')
            ->willReturnMap([
                [ActiveSessionDocument::class, 'machine-1', $sessionDocument],
                [CoinInventoryProjectionDocument::class, 'machine-1', $coinInventoryDocument],
            ]);

        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(SlotProjectionDocument::class)
            ->willReturn($repository);

        $documentManager->expects(self::once())
            ->method('flush');

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', self::callback(static fn (SlotCode $code): bool => '11' === $code->value()))
            ->willReturn(null);
        $slotRepository->expects(self::never())
            ->method('save');

        $handler = new VendProductCommandHandler($documentManager, $coinInventoryRepository, new ChangeAvailabilityChecker(), $slotRepository);

        $result = $handler->handle(new VendProductCommand('machine-1', 'session-1'));

        self::assertSame('completed', $result->status);
        self::assertSame('product-1', $result->productId);
        self::assertSame('11', $result->slotCode);
        self::assertSame(100, $result->priceCents);
        $change = $result->changeDispensed;
        $totalChange = 0;
        foreach ($change as $denomination => $quantity) {
            $totalChange += (int) $denomination * (int) $quantity;
        }

        self::assertSame(25, $totalChange);
        self::assertGreaterThan(0, $change[25] ?? 0);
        self::assertSame([], $result->returnedCoins);

        self::assertSame(1, $slotDocument->quantity());
        self::assertSame('available', $slotDocument->status());

        self::assertSame(0, $result->session->balanceCents);
        self::assertNull($result->session->selectedProductId);
        self::assertNull($result->session->selectedSlotCode);

        $available = $coinInventoryDocument->available();
        self::assertSame(1, $available[100] ?? 0);
        self::assertSame(2, $available[25] ?? 0);
    }

    public function testItCancelsWhenExactChangeUnavailable(): void
    {
        $sessionDocument = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
            state: VendingSessionState::Collecting->value,
            balanceCents: 200,
            insertedCoins: [100 => 2],
            selectedProductId: 'product-1',
            selectedSlotCode: '11',
            changePlan: null,
        );

        $slotDocument = new SlotProjectionDocument(
            machineId: 'machine-1',
            slotCode: '11',
            capacity: 10,
            recommendedSlotQuantity: 6,
            quantity: 2,
            status: 'available',
            lowStock: false,
            productId: 'product-1',
            productName: 'Soda',
            priceCents: 135,
        );

        $coinInventoryDocument = new CoinInventoryProjectionDocument(
            'machine-1',
            [10 => 1, 5 => 1],
            [],
            false,
        );

        $repository = $this->createMock(DocumentRepository::class);
        $repository->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls($slotDocument, $slotDocument);

        $coinInventoryRepository = $this->createMock(CoinInventoryRepository::class);
        $coinInventoryRepository->expects(self::once())
            ->method('find')
            ->with('machine-1')
            ->willReturn(new CoinInventorySnapshot('machine-1', [10 => 1, 5 => 1], [], false, new DateTimeImmutable('-1 minute')));
        $coinInventoryRepository->expects(self::never())
            ->method('save');

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::exactly(2))
            ->method('find')
            ->willReturnMap([
                [ActiveSessionDocument::class, 'machine-1', $sessionDocument],
                [CoinInventoryProjectionDocument::class, 'machine-1', $coinInventoryDocument],
            ]);
        $documentManager->expects(self::exactly(2))
            ->method('getRepository')
            ->with(SlotProjectionDocument::class)
            ->willReturn($repository);
        $documentManager->expects(self::once())
            ->method('flush');

        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(2),
            restockThreshold: RestockThreshold::fromInt(1),
            status: SlotStatus::Reserved,
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', SlotCode::fromString('11'))
            ->willReturn($slot);
        $slotRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (InventorySlot $released): bool => !$released->status()->isReserved()), 'machine-1');

        $handler = new VendProductCommandHandler($documentManager, $coinInventoryRepository, new ChangeAvailabilityChecker(), $slotRepository);

        $result = $handler->handle(new VendProductCommand('machine-1', 'session-1'));

        self::assertSame('cancelled_insufficient_change', $result->status);
        self::assertSame([100 => 2], $result->returnedCoins);
        self::assertSame([], $result->changeDispensed);
        self::assertSame('product-1', $result->productId);
        self::assertSame('11', $result->slotCode);
        self::assertSame(0, $result->session->balanceCents);
        self::assertNull($result->session->selectedSlotCode);

        self::assertSame(2, $slotDocument->quantity());
        self::assertSame([10 => 1, 5 => 1], $coinInventoryDocument->available());
    }

    public function testItFailsWhenBalanceIsInsufficient(): void
    {
        $sessionDocument = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
            state: VendingSessionState::Collecting->value,
            balanceCents: 25,
            insertedCoins: [25 => 1],
            selectedProductId: 'product-1',
            selectedSlotCode: '11',
            changePlan: null,
        );

        $slotDocument = new SlotProjectionDocument(
            machineId: 'machine-1',
            slotCode: '11',
            capacity: 10,
            recommendedSlotQuantity: 6,
            quantity: 2,
            status: 'available',
            lowStock: false,
            productId: 'product-1',
            productName: 'Soda',
            priceCents: 100,
        );

        $repository = $this->createMock(DocumentRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->willReturn($slotDocument);

        $coinInventoryRepository = $this->createMock(CoinInventoryRepository::class);
        $coinInventoryRepository->expects(self::never())
            ->method('find');
        $coinInventoryRepository->expects(self::never())
            ->method('save');

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn($sessionDocument);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->willReturn($repository);

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::never())
            ->method('findByMachineAndCode');
        $slotRepository->expects(self::never())
            ->method('save');

        $handler = new VendProductCommandHandler($documentManager, $coinInventoryRepository, new ChangeAvailabilityChecker(), $slotRepository);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient balance for selected product.');

        $handler->handle(new VendProductCommand('machine-1', 'session-1'));
    }

    public function testItUpdatesInventorySlotWhenAggregateExists(): void
    {
        $sessionDocument = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
            state: VendingSessionState::Collecting->value,
            balanceCents: 125,
            insertedCoins: [100 => 1, 25 => 1],
            selectedProductId: 'product-1',
            selectedSlotCode: '11',
            changePlan: null,
        );

        $slotDocument = new SlotProjectionDocument(
            machineId: 'machine-1',
            slotCode: '11',
            capacity: 10,
            recommendedSlotQuantity: 6,
            quantity: 2,
            status: 'available',
            lowStock: false,
            productId: 'product-1',
            productName: 'Soda',
            priceCents: 100,
        );

        $coinInventoryDocument = new CoinInventoryProjectionDocument(
            'machine-1',
            [25 => 2, 10 => 5, 5 => 5],
            [],
            false,
        );

        $repository = $this->createMock(DocumentRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->willReturn($slotDocument);

        $coinInventoryRepository = $this->createMock(CoinInventoryRepository::class);
        $coinInventoryRepository->expects(self::once())
            ->method('find')
            ->with('machine-1')
            ->willReturn(new CoinInventorySnapshot('machine-1', [25 => 2, 10 => 5, 5 => 5], [], false, new DateTimeImmutable('-1 minute')));
        $coinInventoryRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (CoinInventorySnapshot $snapshot): bool {
                return 1 === ($snapshot->available[100] ?? 0);
            }));

        $slotAggregate = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(2),
            restockThreshold: RestockThreshold::fromInt(1),
            status: SlotStatus::Available,
            productId: ProductId::fromString('product-1'),
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', self::callback(static fn (SlotCode $code): bool => '11' === $code->value()))
            ->willReturn($slotAggregate);
        $slotRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (InventorySlot $slot): bool {
                return 1 === $slot->quantity()->value();
            }), 'machine-1');

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::exactly(2))
            ->method('find')
            ->willReturnMap([
                [ActiveSessionDocument::class, 'machine-1', $sessionDocument],
                [CoinInventoryProjectionDocument::class, 'machine-1', $coinInventoryDocument],
            ]);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->willReturn($repository);
        $documentManager->expects(self::once())
            ->method('flush');

        $handler = new VendProductCommandHandler($documentManager, $coinInventoryRepository, new ChangeAvailabilityChecker(), $slotRepository);

        $result = $handler->handle(new VendProductCommand('machine-1', 'session-1'));

        self::assertSame('completed', $result->status);
        self::assertSame(1, $slotDocument->quantity());
    }
}
