<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Machine\Application\GetMachineState;

use App\VendingMachine\Machine\Application\GetMachineState\GetMachineStateQueryHandler;
use App\VendingMachine\Machine\Application\GetMachineState\MachineStateView;
use App\VendingMachine\Machine\Application\Service\MachineStateProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetMachineStateQueryHandlerTest extends TestCase
{
    public function testItReturnsMachineStateView(): void
    {
        $expectedView = new MachineStateView(
            machineId: 'vm-test',
            timestamp: new DateTimeImmutable('2024-01-01T10:00:00Z'),
            session: null,
            catalog: [
                ['product_id' => 'prod-1', 'name' => 'Water'],
            ],
            coins: [
                'available' => [25 => 4],
                'reserved' => [],
            ],
            alerts: ['insufficient_change' => false],
        );

        $provider = new class($expectedView) implements MachineStateProvider {
            public function __construct(private MachineStateView $view)
            {
            }

            public function currentState(): MachineStateView
            {
                return $this->view;
            }
        };

        $handler = new GetMachineStateQueryHandler($provider);

        $result = $handler();

        self::assertSame($expectedView, $result);
    }
}
