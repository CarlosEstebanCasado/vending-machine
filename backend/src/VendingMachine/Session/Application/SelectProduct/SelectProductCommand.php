<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\SelectProduct;

final class SelectProductCommand
{
    public function __construct(
        public readonly string $machineId,
        public readonly string $sessionId,
        public readonly string $productId,
    ) {
    }
}
