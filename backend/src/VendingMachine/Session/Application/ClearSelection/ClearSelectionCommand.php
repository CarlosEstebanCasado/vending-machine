<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\ClearSelection;

final class ClearSelectionCommand
{
    public function __construct(
        public readonly string $machineId,
        public readonly string $sessionId,
    ) {
    }
}
