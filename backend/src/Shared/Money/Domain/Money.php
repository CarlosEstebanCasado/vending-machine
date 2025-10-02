<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain;

use InvalidArgumentException;

final class Money
{
    private int $amountInCents;

    private function __construct(int $amountInCents)
    {
        if ($amountInCents < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        $this->amountInCents = $amountInCents;
    }

    public static function fromCents(int $amountInCents): self
    {
        return new self($amountInCents);
    }

    public static function fromFloat(float $amount): self
    {
        return new self((int) round($amount * 100));
    }

    public function add(self $other): self
    {
        return new self($this->amountInCents + $other->amountInCents);
    }

    public function subtract(self $other): self
    {
        if ($other->amountInCents > $this->amountInCents) {
            throw new InvalidArgumentException('Resulting amount cannot be negative.');
        }

        return new self($this->amountInCents - $other->amountInCents);
    }

    public function equals(self $other): bool
    {
        return $this->amountInCents === $other->amountInCents;
    }

    public function compareTo(self $other): int
    {
        return $this->amountInCents <=> $other->amountInCents;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function differenceInCents(self $other): int
    {
        return $this->amountInCents - $other->amountInCents;
    }

    public function amountInCents(): int
    {
        return $this->amountInCents;
    }

    public function toFloat(): float
    {
        return $this->amountInCents / 100;
    }
}
