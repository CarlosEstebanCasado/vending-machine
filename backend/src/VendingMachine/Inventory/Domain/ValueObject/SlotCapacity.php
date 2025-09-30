<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Domain\ValueObject;

final class SlotCapacity
{
    private function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Slot capacity must be greater than zero.');
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
}
