<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Application;

use App\Tests\Unit\VendingMachine\Inventory\Domain\InventorySlotMother;
use App\VendingMachine\Inventory\Domain\InventorySlot;
use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Session\Application\SelectProduct\SelectProductCommand;
use App\VendingMachine\Session\Application\SelectProduct\SelectProductCommandHandler;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use DomainException;
use PHPUnit\Framework\TestCase;

final class SelectProductCommandHandlerTest extends TestCase
{
    public function testItAssignsSelectedProductToActiveSession(): void
    {
        $document = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
            state: VendingSessionState::Collecting->value,
            balanceCents: 0,
            insertedCoins: [],
            selectedProductId: null,
            selectedSlotCode: null,
            changePlan: null,
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn($document);

        $projectionRepository = $this->createMock(DocumentRepository::class);
        $projectionRepository->expects(self::once())
            ->method('findOneBy')
            ->willReturn(null);

        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(SlotProjectionDocument::class)
            ->willReturn($projectionRepository);

        $documentManager->expects(self::once())
            ->method('flush');

        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(5),
            restockThreshold: RestockThreshold::fromInt(2),
            status: SlotStatus::Available,
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', SlotCode::fromString('11'))
            ->willReturn($slot);
        $slotRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (InventorySlot $reservedSlot): bool {
                return $reservedSlot->status()->isReserved();
            }), 'machine-1');

        $handler = new SelectProductCommandHandler($documentManager, $slotRepository);

        $result = $handler->handle(new SelectProductCommand('machine-1', 'session-1', 'product-1', '11'));

        self::assertSame('product-1', $document->selectedProductId());
        self::assertSame('product-1', $result->selectedProductId);
        self::assertSame('11', $document->selectedSlotCode());
        self::assertSame('11', $result->selectedSlotCode);
    }

    public function testItFailsWhenActiveSessionIsMissing(): void
    {
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn(null);

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::never())->method('findByMachineAndCode');

        $handler = new SelectProductCommandHandler($documentManager, $slotRepository);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No active session found for this machine.');

        $handler->handle(new SelectProductCommand('machine-1', 'session-1', 'product-1', '11'));
    }

    public function testItFailsWhenSessionIdDoesNotMatch(): void
    {
        $document = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-2',
            state: VendingSessionState::Collecting->value,
            balanceCents: 0,
            insertedCoins: [],
            selectedProductId: null,
            selectedSlotCode: null,
            changePlan: null,
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn($document);

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::never())->method('findByMachineAndCode');

        $handler = new SelectProductCommandHandler($documentManager, $slotRepository);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The provided session id does not match the active session.');

        $handler->handle(new SelectProductCommand('machine-1', 'session-1', 'product-1', '11'));
    }
}
