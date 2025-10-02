<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Application\Service;

use App\VendingMachine\Machine\Application\GetMachineState\MachineStateView;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;

final class MongoMachineStateProvider implements MachineStateProvider
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly string $machineId = 'vendingmachine-default',
    ) {
    }

    public function currentState(): MachineStateView
    {
        [$session, $sessionUpdatedAt] = $this->buildSession();
        $catalog = $this->buildCatalog();
        [$coins, $insufficientChange, $coinsUpdatedAt] = $this->buildCoins();

        $alerts = [
            'insufficient_change' => $insufficientChange,
            'out_of_stock' => array_values(array_unique(array_filter(array_map(
                static fn (array $slot): ?string => ($slot['product_id'] ?? null) && 0 === ($slot['available_quantity'] ?? 0)
                    ? $slot['product_id']
                    : null,
                $catalog
            )))),
        ];

        $timestampCandidates = array_filter([
            $sessionUpdatedAt,
            $coinsUpdatedAt,
        ]);

        $timestamp = !empty($timestampCandidates)
            ? max($timestampCandidates)
            : new DateTimeImmutable();

        return new MachineStateView(
            machineId: $this->machineId,
            timestamp: $timestamp,
            session: $session,
            catalog: $catalog,
            coins: $coins,
            alerts: $alerts,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array{0: array<string, mixed>|null, 1: ?DateTimeImmutable}
     */
    private function buildSession(): array
    {
        /** @var ActiveSessionDocument|null $document */
        $document = $this->documentManager->find(ActiveSessionDocument::class, $this->machineId);

        if (null === $document || null === $document->sessionId()) {
            return [null, $document?->updatedAt()];
        }

        return [[
            'id' => $document->sessionId(),
            'state' => $document->state(),
            'balance_cents' => $document->balanceCents(),
            'inserted_coins' => $document->insertedCoins(),
            'selected_product_id' => $document->selectedProductId(),
            'change_plan' => $document->changePlan(),
        ], $document->updatedAt()];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCatalog(): array
    {
        $repository = $this->documentManager->getRepository(SlotProjectionDocument::class);

        /** @var SlotProjectionDocument[] $documents */
        $documents = $repository->findBy(['machineId' => $this->machineId], ['slotCode' => 'ASC']);

        $catalog = [];
        foreach ($documents as $document) {
            $catalog[] = [
                'slot_code' => $document->slotCode(),
                'product_id' => $document->productId(),
                'product_name' => $document->productName(),
                'price_cents' => $document->priceCents(),
                'available_quantity' => $document->quantity(),
                'capacity' => $document->capacity(),
                'recommended_slot_quantity' => $document->recommendedSlotQuantity(),
                'status' => $document->status(),
                'low_stock' => $document->lowStock(),
            ];
        }

        return $catalog;
    }

    /**
     * @return array{0: array{available: array<int, int>, reserved: array<int, int>}, 1: bool}
     */
    private function buildCoins(): array
    {
        /** @var CoinInventoryProjectionDocument|null $document */
        $document = $this->documentManager->find(CoinInventoryProjectionDocument::class, $this->machineId);

        if (null === $document) {
            return [[
                'available' => [],
                'reserved' => [],
            ], false, null];
        }

        return [[
            'available' => $document->available(),
            'reserved' => $document->reserved(),
        ], $document->insufficientChange(), $document->updatedAt()];
    }
}
