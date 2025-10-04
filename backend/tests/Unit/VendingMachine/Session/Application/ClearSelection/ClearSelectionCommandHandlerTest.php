<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Application\ClearSelection;

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
use App\VendingMachine\Session\Application\ClearSelection\ClearSelectionCommand;
use App\VendingMachine\Session\Application\ClearSelection\ClearSelectionCommandHandler;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ClearSelectionCommandHandlerTest extends TestCase
{
    public function testItClearsSelectionInActiveSession(): void
    {
        $document = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
            state: VendingSessionState::Collecting->value,
            balanceCents: 100,
            insertedCoins: [100 => 1],
            selectedProductId: 'product-1',
            selectedSlotCode: '11',
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
            ->willReturn(new SlotProjectionDocument(
                machineId: 'machine-1',
                slotCode: '11',
                capacity: 10,
                recommendedSlotQuantity: 8,
                quantity: 5,
                status: SlotStatus::Reserved->value,
                lowStock: false,
                productId: 'product-1',
                productName: 'Water',
                priceCents: 65,
            ));

        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(SlotProjectionDocument::class)
            ->willReturn($projectionRepository);

        $documentManager->expects(self::once())
            ->method('flush');

        $slot = InventorySlot::restore(
            InventorySlotId::fromString('slot-1'),
            SlotCode::fromString('11'),
            SlotCapacity::fromInt(10),
            SlotQuantity::fromInt(5),
            RestockThreshold::fromInt(2),
            SlotStatus::Reserved,
            null,
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', SlotCode::fromString('11'))
            ->willReturn($slot);
        $slotRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (InventorySlot $released): bool => !$released->status()->isReserved()), 'machine-1');

        $handler = new ClearSelectionCommandHandler($documentManager, $slotRepository);

        $result = $handler->handle(new ClearSelectionCommand('machine-1', 'session-1'));

        self::assertNull($document->selectedProductId());
        self::assertNull($document->selectedSlotCode());
        self::assertNull($result->selectedProductId);
        self::assertNull($result->selectedSlotCode);
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

        $handler = new ClearSelectionCommandHandler($documentManager, $slotRepository);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No active session found for this machine.');

        $handler->handle(new ClearSelectionCommand('machine-1', 'session-1'));
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

        $handler = new ClearSelectionCommandHandler($documentManager, $slotRepository);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The provided session id does not match the active session.');

        $handler->handle(new ClearSelectionCommand('machine-1', 'session-1'));
    }
}
