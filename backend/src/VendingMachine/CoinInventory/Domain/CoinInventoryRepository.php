<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Domain;

interface CoinInventoryRepository
{
    public function find(string $machineId): ?CoinInventorySnapshot;

    public function save(CoinInventorySnapshot $snapshot): void;
}
