<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Application\GetMachineState;

use App\VendingMachine\Machine\Application\Service\MachineStateProvider;

final class GetMachineStateQueryHandler
{
    public function __construct(private readonly MachineStateProvider $provider)
    {
    }

    public function __invoke(): MachineStateView
    {
        return $this->provider->currentState();
    }
}
