<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Application\AdjustInventory;

final readonly class AdjustCoinInventoryCommand
{
    /**
     * @param array<int, int> $denominations
     */
    public function __construct(
        public string $machineId,
        public AdjustCoinInventoryOperation $operation,
        public array $denominations,
    ) {
    }
}
