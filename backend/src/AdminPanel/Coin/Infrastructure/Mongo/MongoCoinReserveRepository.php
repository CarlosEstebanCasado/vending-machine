<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Infrastructure\Mongo;

use App\AdminPanel\Coin\Domain\CoinReserveRepository;
use App\AdminPanel\Coin\Domain\CoinReserveSnapshot;
use App\AdminPanel\Coin\Infrastructure\Mongo\Document\CoinReserveDocument;
use Doctrine\ODM\MongoDB\DocumentManager;

final class MongoCoinReserveRepository implements CoinReserveRepository
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function find(string $machineId): ?CoinReserveSnapshot
    {
        /** @var CoinReserveDocument|null $document */
        $document = $this->documentManager->find(CoinReserveDocument::class, $machineId);

        if (null === $document) {
            return null;
        }

        return new CoinReserveSnapshot(
            machineId: $document->machineId(),
            balances: $document->balances(),
            updatedAt: $document->updatedAt(),
        );
    }

    public function save(CoinReserveSnapshot $snapshot): void
    {
        /** @var CoinReserveDocument|null $document */
        $document = $this->documentManager->find(CoinReserveDocument::class, $snapshot->machineId);

        if (null === $document) {
            $document = new CoinReserveDocument($snapshot->machineId, $snapshot->balances, $snapshot->updatedAt);
            $this->documentManager->persist($document);
        } else {
            $document->updateBalances($snapshot->balances, $snapshot->updatedAt);
        }

        $this->documentManager->flush();
    }
}
