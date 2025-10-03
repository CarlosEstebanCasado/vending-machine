<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\StartSession;

final class StartSessionCommand
{
    public function __construct(
        public readonly string $machineId,
    ) {
    }
}
