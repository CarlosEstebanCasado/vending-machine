<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\VendProduct;

use App\VendingMachine\Session\Application\StartSession\StartSessionResult;

final class VendProductResult
{
    /**
     * @param array<int, int> $changeDispensed
     * @param array<int, int> $returnedCoins
     */
    public function __construct(
        public readonly StartSessionResult $session,
        public readonly string $status,
        public readonly ?string $productId,
        public readonly ?string $slotCode,
        public readonly int $priceCents,
        public readonly array $changeDispensed,
        public readonly array $returnedCoins,
    ) {
    }
}
