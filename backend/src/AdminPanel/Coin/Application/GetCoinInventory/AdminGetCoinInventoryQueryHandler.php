<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Application\GetCoinInventory;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use Doctrine\ODM\MongoDB\DocumentManager;

final class AdminGetCoinInventoryQueryHandler
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function __invoke(AdminGetCoinInventoryQuery $query): AdminCoinInventoryResult
    {
        /** @var CoinInventoryProjectionDocument|null $document */
        $document = $this->documentManager->getRepository(CoinInventoryProjectionDocument::class)
            ->findOneBy(['machineId' => $query->machineId]);

        if (null === $document) {
            throw new CoinInventoryNotFound(sprintf('Coin inventory not found for machine "%s".', $query->machineId));
        }

        $balances = [];
        foreach ($document->available() as $denomination => $quantity) {
            $balances[(int) $denomination] = [
                'denomination' => (int) $denomination,
                'available' => (int) $quantity,
                'reserved' => 0,
            ];
        }

        foreach ($document->reserved() as $denomination => $quantity) {
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

        return new AdminCoinInventoryResult(
            machineId: $document->machineId(),
            balances: array_values($balances),
            insufficientChange: $document->insufficientChange(),
            updatedAt: $document->updatedAt()->format(DATE_ATOM),
        );
    }
}
