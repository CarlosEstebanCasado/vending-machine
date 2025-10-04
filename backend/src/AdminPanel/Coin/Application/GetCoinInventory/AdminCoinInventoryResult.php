<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Application\GetCoinInventory;

final readonly class AdminCoinInventoryResult
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
