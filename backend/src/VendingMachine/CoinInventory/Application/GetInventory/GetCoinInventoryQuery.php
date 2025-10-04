<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Application\GetInventory;

final readonly class GetCoinInventoryQuery
{
    public function __construct(
        public string $machineId,
    ) {
    }
}
