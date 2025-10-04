<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\VendProduct;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\CoinInventory\Domain\CoinInventory;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Session\Application\StartSession\StartSessionResult;
use App\VendingMachine\Session\Domain\VendingSession;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;

final class VendProductCommandHandler
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function handle(VendProductCommand $command): VendProductResult
    {
        $sessionDocument = $this->loadActiveSession($command);
        $slotCode = $this->requireSelectedSlot($sessionDocument);

        $session = $sessionDocument->toVendingSession();
        $productId = $this->requireSelectedProductId($session);

        $slotDocument = $this->loadSlot($command->machineId, $slotCode);
        $priceCents = $this->requireProductPrice($slotDocument);
        $this->assertSufficientBalance($session, $priceCents);

        $coinInventoryDocument = $this->loadCoinInventory($command->machineId);

        $available = CoinBundle::fromArray($coinInventoryDocument->available());
        $reserved = CoinBundle::fromArray($coinInventoryDocument->reserved());
        $baseInventory = CoinInventory::restore($available, $reserved);

        $planningInventory = CoinInventory::restore($available, $reserved);
        $planningInventory->deposit($session->insertedCoins());

        $changeAmount = $session->balance()->amountInCents() - $priceCents;

        try {
            $changeBundle = $this->planChangeBundle($planningInventory, $changeAmount);
        } catch (DomainException $exception) {
            return $this->handleInsufficientChange(
                session: $session,
                sessionDocument: $sessionDocument,
                productId: $productId,
                slotCode: $slotCode,
                priceCents: $priceCents,
            );
        }

        return $this->finaliseSuccessfulVend(
            session: $session,
            sessionDocument: $sessionDocument,
            productId: $productId,
            slotCode: $slotCode,
            priceCents: $priceCents,
            changeBundle: $changeBundle,
            baseInventory: $baseInventory,
            coinInventoryDocument: $coinInventoryDocument,
            slotDocument: $slotDocument,
        );
    }

    private function loadActiveSession(VendProductCommand $command): ActiveSessionDocument
    {
        /** @var ActiveSessionDocument|null $sessionDocument */
        $sessionDocument = $this->documentManager->find(ActiveSessionDocument::class, $command->machineId);

        if (null === $sessionDocument || null === $sessionDocument->sessionId()) {
            throw new DomainException('No active session found for this machine.');
        }

        if ($sessionDocument->sessionId() !== $command->sessionId) {
            throw new DomainException('The provided session id does not match the active session.');
        }

        return $sessionDocument;
    }

    private function requireSelectedSlot(ActiveSessionDocument $sessionDocument): string
    {
        $slotCode = $sessionDocument->selectedSlotCode();

        if (null === $slotCode) {
            throw new DomainException('No product selected for this session.');
        }

        return $slotCode;
    }

    private function requireSelectedProductId(VendingSession $session): ProductId
    {
        $productId = $session->selectedProductId();

        if (null === $productId) {
            throw new DomainException('No product selected for this session.');
        }

        return $productId;
    }

    private function loadSlot(string $machineId, string $slotCode): SlotProjectionDocument
    {
        $slotRepository = $this->documentManager->getRepository(SlotProjectionDocument::class);

        /** @var SlotProjectionDocument|null $slotDocument */
        $slotDocument = $slotRepository->findOneBy([
            'machineId' => $machineId,
            'slotCode' => $slotCode,
        ]);

        if (null === $slotDocument) {
            throw new DomainException('Selected slot not found.');
        }

        if ($slotDocument->quantity() <= 0) {
            throw new DomainException('Selected product is out of stock.');
        }

        return $slotDocument;
    }

    private function requireProductPrice(SlotProjectionDocument $slotDocument): int
    {
        $price = $slotDocument->priceCents();

        if (null === $price) {
            throw new DomainException('Selected product has no price.');
        }

        return $price;
    }

    private function assertSufficientBalance(VendingSession $session, int $priceCents): void
    {
        if ($session->balance()->amountInCents() < $priceCents) {
            throw new DomainException('Insufficient balance for selected product.');
        }
    }

    private function loadCoinInventory(string $machineId): CoinInventoryProjectionDocument
    {
        /** @var CoinInventoryProjectionDocument|null $coinInventoryDocument */
        $coinInventoryDocument = $this->documentManager->find(CoinInventoryProjectionDocument::class, $machineId);

        if (null === $coinInventoryDocument) {
            $coinInventoryDocument = new CoinInventoryProjectionDocument($machineId, [], [], false);
            $this->documentManager->persist($coinInventoryDocument);
        }

        return $coinInventoryDocument;
    }

    private function planChangeBundle(CoinInventory $planningInventory, int $changeAmount): CoinBundle
    {
        if ($changeAmount <= 0) {
            return CoinBundle::empty();
        }

        return $planningInventory->planChangeFor(Money::fromCents($changeAmount));
    }

    private function handleInsufficientChange(
        VendingSession $session,
        ActiveSessionDocument $sessionDocument,
        ProductId $productId,
        string $slotCode,
        int $priceCents,
    ): VendProductResult {
        $returnedCoins = $session->returnCoins();
        $sessionSnapshot = $this->createSessionSnapshot($session);

        $sessionDocument->clearSession();
        $this->documentManager->flush();

        return new VendProductResult(
            session: $sessionSnapshot,
            status: 'cancelled_insufficient_change',
            productId: $productId->value(),
            slotCode: $slotCode,
            priceCents: $priceCents,
            changeDispensed: [],
            returnedCoins: $returnedCoins->toArray(),
        );
    }

    private function finaliseSuccessfulVend(
        VendingSession $session,
        ActiveSessionDocument $sessionDocument,
        ProductId $productId,
        string $slotCode,
        int $priceCents,
        CoinBundle $changeBundle,
        CoinInventory $baseInventory,
        CoinInventoryProjectionDocument $coinInventoryDocument,
        SlotProjectionDocument $slotDocument,
    ): VendProductResult {
        $baseInventory->deposit($session->insertedCoins());

        if (!$changeBundle->isEmpty()) {
            $baseInventory->reserveChange($changeBundle);
            $baseInventory->commitReserved($changeBundle);
        }

        $slotDocument->dispenseProduct();

        $priceMoney = Money::fromCents($priceCents);
        $session->approvePurchase($priceMoney, $changeBundle);
        $dispensedChange = $session->startDispensing();

        $sessionSnapshot = $this->createSessionSnapshot($session);

        $sessionDocument->clearSession();
        $coinInventoryDocument->applyInventory($baseInventory, false);

        $this->documentManager->flush();

        return new VendProductResult(
            session: $sessionSnapshot,
            status: 'completed',
            productId: $productId->value(),
            slotCode: $slotCode,
            priceCents: $priceCents,
            changeDispensed: $dispensedChange->toArray(),
            returnedCoins: [],
        );
    }

    private function createSessionSnapshot(VendingSession $session): StartSessionResult
    {
        return new StartSessionResult(
            sessionId: $session->id()->value(),
            state: $session->state()->value,
            balanceCents: $session->balance()->amountInCents(),
            insertedCoins: $session->insertedCoins()->toArray(),
            selectedProductId: $session->selectedProductId()?->value(),
            selectedSlotCode: null,
        );
    }
}
