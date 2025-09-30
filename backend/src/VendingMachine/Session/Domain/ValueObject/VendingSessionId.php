<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Domain\ValueObject;

final class VendingSessionId
{
    private string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Session id cannot be empty.');
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
