<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Application\UpdateCoinInventory;

final readonly class AdminUpdateCoinInventoryCommand
{
    /**
     * @param array<int, int> $denominations
     */
    public function __construct(
        public string $machineId,
        public UpdateCoinInventoryOperation $operation,
        public array $denominations,
    ) {
    }
}
