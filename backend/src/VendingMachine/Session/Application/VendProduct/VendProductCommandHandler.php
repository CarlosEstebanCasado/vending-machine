<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\VendProduct;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\CoinInventory\Domain\CoinInventory;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Session\Application\StartSession\StartSessionResult;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;

final class VendProductCommandHandler
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function handle(VendProductCommand $command): VendProductResult
    {
        /** @var ActiveSessionDocument|null $sessionDocument */
        $sessionDocument = $this->documentManager->find(ActiveSessionDocument::class, $command->machineId);

        if (null === $sessionDocument || null === $sessionDocument->sessionId()) {
            throw new DomainException('No active session found for this machine.');
        }

        if ($sessionDocument->sessionId() !== $command->sessionId) {
            throw new DomainException('The provided session id does not match the active session.');
        }

        $slotCode = $sessionDocument->selectedSlotCode();

        if (null === $slotCode) {
            throw new DomainException('No product selected for this session.');
        }

        $session = $sessionDocument->toVendingSession();

        $sessionProductId = $session->selectedProductId();
        if (null === $sessionProductId) {
            throw new DomainException('No product selected for this session.');
        }

        $slotRepository = $this->documentManager->getRepository(SlotProjectionDocument::class);
        /** @var SlotProjectionDocument|null $slotDocument */
        $slotDocument = $slotRepository->findOneBy([
            'machineId' => $command->machineId,
            'slotCode' => $slotCode,
        ]);

        if (null === $slotDocument) {
            throw new DomainException('Selected slot not found.');
        }

        if ($slotDocument->quantity() <= 0) {
            throw new DomainException('Selected product is out of stock.');
        }

        $priceCents = $slotDocument->priceCents();
        if (null === $priceCents) {
            throw new DomainException('Selected product has no price.');
        }

        $balanceCents = $session->balance()->amountInCents();
        if ($balanceCents < $priceCents) {
            throw new DomainException('Insufficient balance for selected product.');
        }

        $coinInventoryDocument = $this->documentManager->find(CoinInventoryProjectionDocument::class, $command->machineId);
        if (null === $coinInventoryDocument) {
            $coinInventoryDocument = new CoinInventoryProjectionDocument($command->machineId, [], [], false);
            $this->documentManager->persist($coinInventoryDocument);
        }

        $available = CoinBundle::fromArray($coinInventoryDocument->available());
        $reserved = CoinBundle::fromArray($coinInventoryDocument->reserved());
        $baseInventory = CoinInventory::restore($available, $reserved);

        $planningInventory = CoinInventory::restore($available, $reserved);
        $planningInventory->deposit($session->insertedCoins());

        $changeBundle = CoinBundle::empty();
        $changeAmount = $balanceCents - $priceCents;

        try {
            if ($changeAmount > 0) {
                $changeBundle = $planningInventory->planChangeFor(Money::fromCents($changeAmount));
            }
        } catch (DomainException $exception) {
            $returnedCoins = $session->returnCoins();
            $sessionSnapshot = new StartSessionResult(
                sessionId: $session->id()->value(),
                state: $session->state()->value,
                balanceCents: $session->balance()->amountInCents(),
                insertedCoins: $session->insertedCoins()->toArray(),
                selectedProductId: $session->selectedProductId()?->value(),
                selectedSlotCode: null,
            );

            $sessionDocument->clearSession();
            $this->documentManager->flush();

            return new VendProductResult(
                session: $sessionSnapshot,
                status: 'cancelled_insufficient_change',
                productId: $sessionProductId->value(),
                slotCode: $slotCode,
                priceCents: $priceCents,
                changeDispensed: [],
                returnedCoins: $returnedCoins->toArray(),
            );
        }

        $baseInventory->deposit($session->insertedCoins());
        if (!$changeBundle->isEmpty()) {
            $baseInventory->reserveChange($changeBundle);
            $baseInventory->commitReserved($changeBundle);
        }

        $slotDocument->dispenseProduct();

        $session->approvePurchase(Money::fromCents($priceCents), $changeBundle);
        $dispensedChange = $session->startDispensing();

        $sessionSnapshot = new StartSessionResult(
            sessionId: $session->id()->value(),
            state: $session->state()->value,
            balanceCents: $session->balance()->amountInCents(),
            insertedCoins: $session->insertedCoins()->toArray(),
            selectedProductId: $session->selectedProductId()?->value(),
            selectedSlotCode: null,
        );

        $sessionDocument->clearSession();
        $coinInventoryDocument->applyInventory($baseInventory, false);

        $this->documentManager->flush();

        return new VendProductResult(
            session: $sessionSnapshot,
            status: 'completed',
            productId: $sessionProductId->value(),
            slotCode: $slotCode,
            priceCents: $priceCents,
            changeDispensed: $dispensedChange->toArray(),
            returnedCoins: [],
        );
    }
}
