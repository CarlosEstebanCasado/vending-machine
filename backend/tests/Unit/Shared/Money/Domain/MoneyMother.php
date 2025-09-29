<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Domain;

use App\Shared\Money\Domain\Money;

final class MoneyMother
{
    public static function fromCents(int $cents = 100): Money
    {
        return Money::fromCents($cents);
    }

    public static function fromFloat(float $amount = 1.0): Money
    {
        return Money::fromFloat($amount);
    }
}
