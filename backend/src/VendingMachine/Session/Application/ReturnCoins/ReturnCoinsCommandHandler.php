<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\ReturnCoins;

use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;

final class ReturnCoinsCommandHandler
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly InventorySlotRepository $slotRepository,
    ) {
    }

    public function handle(ReturnCoinsCommand $command): ReturnCoinsResult
    {
        $document = $this->loadActiveSession($command);
        $previousSlotCode = $document->selectedSlotCode();

        $session = $document->toVendingSession();
        $returnedCoins = $session->returnCoins();

        $document->applySession($session);

        $this->releasePreviousSlot($command->machineId, $previousSlotCode);

        $this->documentManager->flush();

        return new ReturnCoinsResult(
            sessionId: $session->id()->value(),
            state: $session->state()->value,
            balanceCents: $session->balance()->amountInCents(),
            insertedCoins: $session->insertedCoins()->toArray(),
            selectedProductId: $session->selectedProductId()?->value(),
            selectedSlotCode: $document->selectedSlotCode(),
            returnedCoins: $returnedCoins->toArray(),
        );
    }

    private function loadActiveSession(ReturnCoinsCommand $command): ActiveSessionDocument
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

    private function releasePreviousSlot(string $machineId, ?string $slotCode): void
    {
        if (null === $slotCode) {
            return;
        }

        $this->releaseSlot($machineId, $slotCode);
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
