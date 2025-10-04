<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Domain;

use DateTimeImmutable;

final readonly class CoinInventorySnapshot
{
    /**
     * @param array<int, int> $available
     * @param array<int, int> $reserved
     */
    public function __construct(
        public string $machineId,
        public array $available,
        public array $reserved,
        public bool $insufficientChange,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
