<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Application\GetInventory;

final readonly class CoinInventoryResult
{
    /**
     * @param array<int, array{denomination:int, available:int, reserved:int}> $balances
     */
    public function __construct(
        public string $machineId,
        public array $balances,
        public bool $insufficientChange,
        public string $updatedAt,
    ) {
    }
}
