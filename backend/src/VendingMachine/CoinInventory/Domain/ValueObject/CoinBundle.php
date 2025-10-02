<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Domain\ValueObject;

use App\Shared\Money\Domain\Money;
use DomainException;
use InvalidArgumentException;

final class CoinBundle
{
    /**
     * @var array<int, CoinQuantity>
     */
    private array $coins;

    private function __construct(array $coins)
    {
        $this->coins = [];

        foreach (CoinDenomination::sortedDescending() as $denomination) {
            $value = $coins[$denomination->value] ?? 0;

            if (!is_int($value)) {
                throw new InvalidArgumentException('Coin quantity must be an integer.');
            }

            $this->coins[$denomination->value] = CoinQuantity::fromInt($value);
        }
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<int, int> $coins
     */
    public static function fromArray(array $coins): self
    {
        return new self($coins);
    }

    public function quantityFor(CoinDenomination $denomination): CoinQuantity
    {
        return $this->coins[$denomination->value];
    }

    public function add(self $other): self
    {
        $result = [];

        foreach (CoinDenomination::sortedDescending() as $denomination) {
            $result[$denomination->value] = $this->quantityFor($denomination)->add($other->quantityFor($denomination))->value();
        }

        return new self($result);
    }

    public function subtract(self $other): self
    {
        $result = [];

        foreach (CoinDenomination::sortedDescending() as $denomination) {
            $result[$denomination->value] = $this->quantityFor($denomination)->subtract($other->quantityFor($denomination))->value();
        }

        return new self($result);
    }

    public function includesAtLeast(self $other): bool
    {
        foreach (CoinDenomination::sortedDescending() as $denomination) {
            if ($this->quantityFor($denomination)->value() < $other->quantityFor($denomination)->value()) {
                return false;
            }
        }

        return true;
    }

    public function totalAmount(): Money
    {
        $total = 0;

        foreach (CoinDenomination::sortedDescending() as $denomination) {
            $total += $denomination->value * $this->quantityFor($denomination)->value();
        }

        return Money::fromCents($total);
    }

    /**
     * @return array<int, int>
     */
    public function toArray(): array
    {
        $result = [];

        foreach (CoinDenomination::sortedDescending() as $denomination) {
            $quantity = $this->quantityFor($denomination)->value();
            if ($quantity > 0) {
                $result[$denomination->value] = $quantity;
            }
        }

        return $result;
    }

    public function isEmpty(): bool
    {
        foreach ($this->coins as $quantity) {
            if (!$quantity->isZero()) {
                return false;
            }
        }

        return true;
    }

    public function assertContainsOnlyChangeCoins(): void
    {
        foreach (CoinDenomination::changeDenominations() as $denomination) {
            // access to ensure indexes exist
            $this->quantityFor($denomination);
        }

        if ($this->quantityFor(CoinDenomination::OneDollar)->value() > 0) {
            throw new DomainException('Dollar coins cannot be dispensed as change.');
        }
    }
}
