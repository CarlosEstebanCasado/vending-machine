<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Domain\ValueObject;

use InvalidArgumentException;

final class CoinQuantity
{
    private function __construct(private int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Coin quantity cannot be negative.');
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function subtract(self $other): self
    {
        if ($other->value > $this->value) {
            throw new InvalidArgumentException('Cannot subtract more coins than available.');
        }

        return new self($this->value - $other->value);
    }

    public function isZero(): bool
    {
        return 0 === $this->value;
    }
}
