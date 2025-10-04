<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Domain;

interface CoinReserveRepository
{
    public function find(string $machineId): ?CoinReserveSnapshot;

    public function save(CoinReserveSnapshot $snapshot): void;
}
