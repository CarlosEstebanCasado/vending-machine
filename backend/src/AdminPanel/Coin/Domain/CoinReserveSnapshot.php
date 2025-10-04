<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Domain;

use DateTimeImmutable;

final readonly class CoinReserveSnapshot
{
    /**
     * @param array<int, int> $balances
     */
    public function __construct(
        public string $machineId,
        public array $balances,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
