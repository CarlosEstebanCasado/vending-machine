<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Application\GetCoinInventory;

final readonly class AdminGetCoinInventoryQuery
{
    public function __construct(
        public string $machineId,
    ) {
    }
}
