<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\StartSession;

final class StartSessionResult
{
    /**
     * @param array<int, int> $insertedCoins
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $state,
        public readonly int $balanceCents,
        public readonly array $insertedCoins,
        public readonly ?string $selectedProductId,
        public readonly ?string $selectedSlotCode,
    ) {
    }
}
