<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Domain\ValueObject;

final class SlotQuantity
{
    private function __construct(private int $value)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Slot quantity cannot be negative.');
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

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function add(int $units): self
    {
        if ($units <= 0) {
            throw new \InvalidArgumentException('Units to add must be greater than zero.');
        }

        return new self($this->value + $units);
    }

    public function subtract(int $units): self
    {
        if ($units <= 0) {
            throw new \InvalidArgumentException('Units to subtract must be greater than zero.');
        }

        if ($units > $this->value) {
            throw new \InvalidArgumentException('Cannot subtract more units than currently available.');
        }

        return new self($this->value - $units);
    }

    public function isZero(): bool
    {
        return 0 === $this->value;
    }
}
