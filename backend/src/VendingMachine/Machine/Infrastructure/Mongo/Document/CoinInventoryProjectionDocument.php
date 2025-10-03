<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Infrastructure\Mongo\Document;

use App\VendingMachine\CoinInventory\Domain\CoinInventory;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'machine_coin_inventory')]
class CoinInventoryProjectionDocument
{
    #[ODM\Id(strategy: 'NONE', type: 'string')]
    private string $machineId;

    #[ODM\Field(type: 'hash')]
    private array $available = [];

    #[ODM\Field(type: 'hash')]
    private array $reserved = [];

    #[ODM\Field(type: 'bool')]
    private bool $insufficientChange = false;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<int, int> $available
     * @param array<int, int> $reserved
     */
    public function __construct(
        string $machineId,
        array $available,
        array $reserved,
        bool $insufficientChange,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->machineId = $machineId;
        $this->available = array_map('intval', $available);
        $this->reserved = array_map('intval', $reserved);
        $this->insufficientChange = $insufficientChange;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function machineId(): string
    {
        return $this->machineId;
    }

    /**
     * @return array<int, int>
     */
    public function available(): array
    {
        $result = [];

        foreach ($this->available as $denomination => $quantity) {
            $result[(int) $denomination] = (int) $quantity;
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    public function reserved(): array
    {
        $result = [];

        foreach ($this->reserved as $denomination => $quantity) {
            $result[(int) $denomination] = (int) $quantity;
        }

        return $result;
    }

    public function insufficientChange(): bool
    {
        return $this->insufficientChange;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function applyInventory(CoinInventory $inventory, bool $insufficientChange): void
    {
        $this->available = $inventory->availableCoins()->toArray();
        $this->reserved = $inventory->reservedCoins()->toArray();
        $this->insufficientChange = $insufficientChange;
        $this->updatedAt = new DateTimeImmutable();
    }
}
