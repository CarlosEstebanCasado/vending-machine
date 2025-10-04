<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\SelectProduct;

use App\VendingMachine\Inventory\Domain\InventorySlot;
use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Session\Application\StartSession\StartSessionResult;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;

final class SelectProductCommandHandler
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly InventorySlotRepository $slotRepository,
    ) {
    }

    public function handle(SelectProductCommand $command): StartSessionResult
    {
        $document = $this->loadActiveSession($command);
        $previousSlotCode = $document->selectedSlotCode();

        $slot = $this->loadSlot($command->machineId, $command->slotCode);
        $this->assertSlotIsReservable($slot, $command->slotCode, $previousSlotCode);

        $this->releasePreviousSlot($command->machineId, $command->slotCode, $previousSlotCode);
        $this->reserveSlot($command->machineId, $command->slotCode, $slot);

        $session = $document->toVendingSession();
        $session->selectProduct(ProductId::fromString($command->productId));

        $document->applySession($session, $command->slotCode);

        $this->documentManager->flush();

        return new StartSessionResult(
            sessionId: $session->id()->value(),
            state: $session->state()->value,
            balanceCents: $session->balance()->amountInCents(),
            insertedCoins: $session->insertedCoins()->toArray(),
            selectedProductId: $session->selectedProductId()?->value(),
            selectedSlotCode: $document->selectedSlotCode(),
        );
    }

    private function loadActiveSession(SelectProductCommand $command): ActiveSessionDocument
    {
        /** @var ActiveSessionDocument|null $document */
        $document = $this->documentManager->find(ActiveSessionDocument::class, $command->machineId);

        if (null === $document || null === $document->sessionId()) {
            throw new DomainException('No active session found for this machine.');
        }

        if ($document->sessionId() !== $command->sessionId) {
            throw new DomainException('The provided session id does not match the active session.');
        }

        return $document;
    }

    private function loadSlot(string $machineId, string $slotCode): InventorySlot
    {
        $slot = $this->slotRepository->findByMachineAndCode($machineId, SlotCode::fromString($slotCode));

        if (null === $slot) {
            throw new DomainException(sprintf('Slot "%s" not found for machine "%s".', $slotCode, $machineId));
        }

        if ($slot->quantity()->isZero()) {
            throw new DomainException('Selected slot is empty.');
        }

        if ($slot->status()->isDisabled()) {
            throw new DomainException('Selected slot is disabled.');
        }

        return $slot;
    }

    private function assertSlotIsReservable(
        InventorySlot $slot,
        string $requestedSlotCode,
        ?string $previousSlotCode,
    ): void {
        if (!$slot->status()->isReserved() || $previousSlotCode === $requestedSlotCode) {
            return;
        }

        throw new DomainException('Selected slot is currently reserved.');
    }

    private function releasePreviousSlot(string $machineId, string $requestedSlotCode, ?string $previousSlotCode): void
    {
        if (null === $previousSlotCode || $previousSlotCode === $requestedSlotCode) {
            return;
        }

        $this->releaseSlot($machineId, $previousSlotCode);
    }

    private function reserveSlot(string $machineId, string $slotCode, InventorySlot $slot): void
    {
        $slot->markReserved();
        $this->slotRepository->save($slot, $machineId);
        $this->syncProjection($machineId, $slotCode, $slot);
    }

    private function releaseSlot(string $machineId, string $slotCode): void
    {
        $slot = $this->slotRepository->findByMachineAndCode($machineId, SlotCode::fromString($slotCode));

        if (null === $slot) {
            return;
        }

        if ($slot->quantity()->isZero()) {
            $slot->disable();
        } else {
            $slot->markAvailable();
        }

        $this->slotRepository->save($slot, $machineId);
        $this->syncProjection($machineId, $slotCode, $slot);
    }

    private function syncProjection(string $machineId, string $slotCode, InventorySlot $slot): void
    {
        $repository = $this->documentManager->getRepository(SlotProjectionDocument::class);

        /** @var SlotProjectionDocument|null $projection */
        $projection = $repository->findOneBy([
            'machineId' => $machineId,
            'slotCode' => $slotCode,
        ]);

        if (null === $projection) {
            return;
        }

        $projection->syncFromInventory(
            slotQuantity: $slot->quantity()->value(),
            slotCapacity: $slot->capacity()->value(),
            status: $slot->status()->value,
            lowStock: $slot->needsRestock(),
            productId: $slot->productId()?->value(),
            productName: $projection->productName(),
            priceCents: $projection->priceCents(),
            recommendedSlotQuantity: $projection->recommendedSlotQuantity(),
        );
    }
}
