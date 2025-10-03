<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\ReturnCoins;

final class ReturnCoinsResult
{
    /**
     * @param array<int, int> $insertedCoins
     * @param array<int, int> $returnedCoins
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $state,
        public readonly int $balanceCents,
        public readonly array $insertedCoins,
        public readonly ?string $selectedProductId,
        public readonly ?string $selectedSlotCode,
        public readonly array $returnedCoins,
    ) {
    }
}
