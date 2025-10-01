<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Application\GetMachineState;

use DateTimeImmutable;

final class MachineStateView
{
    /**
     * @param array<int, array<string, mixed>> $catalog
     * @param array{available: array<int, int>, reserved: array<int, int>} $coins
     * @param array<string, mixed> $alerts
     */
    public function __construct(
        private readonly string $machineId,
        private readonly DateTimeImmutable $timestamp,
        private readonly ?array $session,
        private readonly array $catalog,
        private readonly array $coins,
        private readonly array $alerts,
    ) {
    }

    public function machineId(): string
    {
        return $this->machineId;
    }

    public function timestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function session(): ?array
    {
        return $this->session;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return $this->catalog;
    }

    /**
     * @return array{available: array<int, int>, reserved: array<int, int>}
     */
    public function coins(): array
    {
        return $this->coins;
    }

    /**
     * @return array<string, mixed>
     */
    public function alerts(): array
    {
        return $this->alerts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'machine_id' => $this->machineId,
            'timestamp' => $this->timestamp->format(DATE_ATOM),
            'session' => $this->session,
            'catalog' => $this->catalog,
            'coins' => $this->coins,
            'alerts' => $this->alerts,
        ];
    }
}
