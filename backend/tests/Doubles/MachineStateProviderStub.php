<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\VendingMachine\Machine\Application\GetMachineState\MachineStateView;
use App\VendingMachine\Machine\Application\Service\MachineStateProvider;
use DateTimeImmutable;

final class MachineStateProviderStub implements MachineStateProvider
{
    public function currentState(): MachineStateView
    {
        return new MachineStateView(
            machineId: 'stub-machine',
            timestamp: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            session: null,
            catalog: [],
            coins: [
                'available' => [],
                'reserved' => [],
            ],
            alerts: [
                'insufficient_change' => false,
                'out_of_stock' => [],
            ],
        );
    }
}
