<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Application\Service;

use App\VendingMachine\Machine\Application\GetMachineState\MachineStateView;

interface MachineStateProvider
{
    public function currentState(): MachineStateView;
}
