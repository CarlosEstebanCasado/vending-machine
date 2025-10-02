<?php

declare(strict_types=1);

namespace App\VendingMachine\Product\Domain\ValueObject;

use InvalidArgumentException;

final class ProductName
{
    private const MAX_LENGTH = 100;

    private function __construct(private string $value)
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            throw new InvalidArgumentException('Product name cannot be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('Product name cannot exceed %d characters.', self::MAX_LENGTH));
        }

        $this->value = $trimmed;
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
