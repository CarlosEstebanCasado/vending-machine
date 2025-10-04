<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Infrastructure\Mongo;

use App\VendingMachine\CoinInventory\Domain\CoinInventoryRepository;
use App\VendingMachine\CoinInventory\Domain\CoinInventorySnapshot;
use App\VendingMachine\CoinInventory\Infrastructure\Mongo\Document\CoinInventoryDocument;
use Doctrine\ODM\MongoDB\DocumentManager;

final class MongoCoinInventoryRepository implements CoinInventoryRepository
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function find(string $machineId): ?CoinInventorySnapshot
    {
        /** @var CoinInventoryDocument|null $document */
        $document = $this->documentManager->find(CoinInventoryDocument::class, $machineId);

        if (null === $document) {
            return null;
        }

        return new CoinInventorySnapshot(
            machineId: $document->machineId(),
            available: $document->available(),
            reserved: $document->reserved(),
            updatedAt: $document->updatedAt(),
        );
    }

    public function save(CoinInventorySnapshot $snapshot): void
    {
        /** @var CoinInventoryDocument|null $document */
        $document = $this->documentManager->find(CoinInventoryDocument::class, $snapshot->machineId);

        if (null === $document) {
            $document = new CoinInventoryDocument(
                machineId: $snapshot->machineId,
                available: $snapshot->available,
                reserved: $snapshot->reserved,
                updatedAt: $snapshot->updatedAt,
            );
            $this->documentManager->persist($document);
        } else {
            $document->updateInventory($snapshot->available, $snapshot->reserved, $snapshot->updatedAt);
        }

        $this->documentManager->flush();
    }
}
