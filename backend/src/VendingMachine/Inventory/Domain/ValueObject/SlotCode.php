<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Domain\ValueObject;

final class SlotCode
{
    private const MAX_LENGTH = 10;

    private function __construct(private string $value)
    {
        $normalized = strtoupper(trim($value));
        if ('' === $normalized) {
            throw new \InvalidArgumentException('Slot code cannot be empty.');
        }

        if (strlen($normalized) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Slot code cannot exceed %d characters.', self::MAX_LENGTH));
        }

        if (!preg_match('/^[A-Z0-9-]+$/', $normalized)) {
            throw new \InvalidArgumentException('Slot code must contain only alphanumeric characters or hyphens.');
        }

        $this->value = $normalized;
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
