<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Infrastructure\Mongo\Document;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'coin_reserves')]
class CoinInventoryDocument
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
    public function __construct(string $machineId, array $available = [], array $reserved = [], bool $insufficientChange = false, ?DateTimeImmutable $updatedAt = null)
    {
        $this->machineId = $machineId;
        $this->setAvailable($available);
        $this->setReserved($reserved);
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
        return $this->normalize($this->available);
    }

    /**
     * @return array<int, int>
     */
    public function reserved(): array
    {
        return $this->normalize($this->reserved);
    }

    public function insufficientChange(): bool
    {
        return $this->insufficientChange;
    }

    /**
     * @param array<int, int> $available
     * @param array<int, int> $reserved
     */
    public function updateInventory(array $available, array $reserved, bool $insufficientChange, ?DateTimeImmutable $updatedAt = null): void
    {
        $this->setAvailable($available);
        $this->setReserved($reserved);
        $this->insufficientChange = $insufficientChange;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param array<int, int> $input
     */
    private function setAvailable(array $input): void
    {
        $this->available = $this->normalize($input);
    }

    /**
     * @param array<int, int> $input
     */
    private function setReserved(array $input): void
    {
        $this->reserved = $this->normalize($input);
    }

    /**
     * @param array<int, int> $input
     *
     * @return array<int, int>
     */
    private function normalize(array $input): array
    {
        $normalized = [];

        foreach ($input as $denomination => $quantity) {
            $normalized[(int) $denomination] = (int) $quantity;
        }

        krsort($normalized);

        return $normalized;
    }
}
