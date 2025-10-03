<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\VendProduct;

final class VendProductCommand
{
    public function __construct(
        public readonly string $machineId,
        public readonly string $sessionId,
    ) {
    }
}
