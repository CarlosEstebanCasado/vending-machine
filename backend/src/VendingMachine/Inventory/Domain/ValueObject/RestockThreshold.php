<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Domain\ValueObject;

final class RestockThreshold
{
    private function __construct(private int $value)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Restock threshold cannot be negative.');
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
