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

    public function __invoke(GetCoinInventoryQuery $query): CoinInventoryResult
    {
        $snapshot = $this->coinInventoryRepository->find($query->machineId);

        /** @var CoinInventoryProjectionDocument|null $document */
        $document = $this->documentManager->find(CoinInventoryProjectionDocument::class, $query->machineId);

        if (null === $snapshot && null === $document) {
            throw new CoinInventoryNotFound(sprintf('Coin inventory not found for machine "%s".', $query->machineId));
        }

        if (null === $snapshot && null !== $document) {
            $snapshot = new CoinInventorySnapshot(
                machineId: $document->machineId(),
                available: $document->available(),
                reserved: $document->reserved(),
                updatedAt: $document->updatedAt(),
            );
        }

        $available = $snapshot?->available ?? [];
        $reserved = $snapshot?->reserved ?? [];

        $balances = [];
        foreach ($available as $denomination => $quantity) {
            $balances[(int) $denomination] = [
                'denomination' => (int) $denomination,
                'available' => (int) $quantity,
                'reserved' => 0,
            ];
        }

        foreach ($reserved as $denomination => $quantity) {
            $denomination = (int) $denomination;
            if (!isset($balances[$denomination])) {
                $balances[$denomination] = [
                    'denomination' => $denomination,
                    'available' => 0,
                    'reserved' => (int) $quantity,
                ];
                continue;
            }

            $balances[$denomination]['reserved'] = (int) $quantity;
        }

        krsort($balances);

        return new CoinInventoryResult(
            machineId: $snapshot?->machineId ?? $query->machineId,
            balances: array_values($balances),
            insufficientChange: $document?->insufficientChange() ?? false,
            updatedAt: ($snapshot?->updatedAt ?? $document?->updatedAt() ?? new DateTimeImmutable())->format(DATE_ATOM),
        );
    }
}
