<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Application\GetInventory;

use App\VendingMachine\CoinInventory\Domain\CoinInventoryRepository;
use App\VendingMachine\CoinInventory\Domain\CoinInventorySnapshot;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;

final class GetCoinInventoryQueryHandler
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly CoinInventoryRepository $coinInventoryRepository,
    ) {
    }

    public function handle(GetCoinInventoryQuery $query): CoinInventoryResult
    {
        [$snapshot, $document] = $this->resolveSnapshot($query->machineId);
        $balances = $this->buildBalances($snapshot);

        return new CoinInventoryResult(
            machineId: $snapshot?->machineId ?? $query->machineId,
            balances: array_values($balances),
            insufficientChange: $snapshot?->insufficientChange ?? $document?->insufficientChange() ?? false,
            updatedAt: ($snapshot?->updatedAt ?? $document?->updatedAt() ?? new DateTimeImmutable())->format(DATE_ATOM),
        );
    }

    /**
     * @return array{0: ?CoinInventorySnapshot, 1: ?CoinInventoryProjectionDocument}
     */
    private function resolveSnapshot(string $machineId): array
    {
        $snapshot = $this->coinInventoryRepository->find($machineId);

        /** @var CoinInventoryProjectionDocument|null $document */
        $document = $this->documentManager->find(CoinInventoryProjectionDocument::class, $machineId);

        if (null === $snapshot && null === $document) {
            throw new CoinInventoryNotFound(sprintf('Coin inventory not found for machine "%s".', $machineId));
        }

        if (null === $snapshot && null !== $document) {
            $snapshot = new CoinInventorySnapshot(
                machineId: $document->machineId(),
                available: $document->available(),
                reserved: $document->reserved(),
                insufficientChange: $document->insufficientChange(),
                updatedAt: $document->updatedAt(),
            );
        }

        return [$snapshot, $document];
    }

    /**
     * @return array<int, array{denomination:int, available:int, reserved:int}>
     */
    private function buildBalances(?CoinInventorySnapshot $snapshot): array
    {
        $balances = [];

        foreach ($snapshot?->available ?? [] as $denomination => $quantity) {
            $balances[(int) $denomination] = [
                'denomination' => (int) $denomination,
                'available' => (int) $quantity,
                'reserved' => 0,
            ];
        }

        foreach ($snapshot?->reserved ?? [] as $denomination => $quantity) {
            $denomination = (int) $denomination;
            $reservedQuantity = (int) $quantity;

            if (!isset($balances[$denomination])) {
                $balances[$denomination] = [
                    'denomination' => $denomination,
                    'available' => 0,
                    'reserved' => $reservedQuantity,
                ];
                continue;
            }

            $balances[$denomination]['reserved'] = $reservedQuantity;
        }

        krsort($balances);

        return $balances;
    }
}
