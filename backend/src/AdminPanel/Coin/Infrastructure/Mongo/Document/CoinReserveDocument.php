<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Infrastructure\Mongo\Document;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'coin_reserves')]
class CoinReserveDocument
{
    #[ODM\Id(strategy: 'NONE', type: 'string')]
    private string $machineId;

    #[ODM\Field(type: 'hash')]
    private array $balances = [];

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<int, int> $balances
     */
    public function __construct(string $machineId, array $balances = [], ?DateTimeImmutable $updatedAt = null)
    {
        $this->machineId = $machineId;
        $this->setBalances($balances);
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function machineId(): string
    {
        return $this->machineId;
    }

    /**
     * @return array<int, int>
     */
    public function balances(): array
    {
        $result = [];

        foreach ($this->balances as $denomination => $quantity) {
            $result[(int) $denomination] = (int) $quantity;
        }

        return $result;
    }

    /**
     * @param array<int, int> $balances
     */
    public function updateBalances(array $balances, ?DateTimeImmutable $updatedAt = null): void
    {
        $this->setBalances($balances);
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param array<int, int> $balances
     */
    private function setBalances(array $balances): void
    {
        $normalized = [];
        foreach ($balances as $denomination => $quantity) {
            $normalized[(int) $denomination] = (int) $quantity;
        }

        $this->balances = $normalized;
    }
}
