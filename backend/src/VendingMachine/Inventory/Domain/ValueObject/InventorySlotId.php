<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Domain\ValueObject;

final class InventorySlotId
{
    private function __construct(private readonly string $value)
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Inventory slot id cannot be empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
