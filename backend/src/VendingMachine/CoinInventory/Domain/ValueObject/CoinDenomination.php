<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Domain\ValueObject;

enum CoinDenomination: int
{
    case OneDollar = 100;
    case TwentyFiveCents = 25;
    case TenCents = 10;
    case FiveCents = 5;

    /**
     * @return list<self>
     */
    public static function sortedDescending(): array
    {
        return [self::OneDollar, self::TwentyFiveCents, self::TenCents, self::FiveCents];
    }

    /**
     * @return list<self>
     */
    public static function changeDenominations(): array
    {
        return [self::TwentyFiveCents, self::TenCents, self::FiveCents];
    }

    public function canBeDispensedAsChange(): bool
    {
        return $this !== self::OneDollar;
    }

    public function label(): string
    {
        return match ($this) {
            self::OneDollar => '$1.00',
            self::TwentyFiveCents => '$0.25',
            self::TenCents => '$0.10',
            self::FiveCents => '$0.05',
        };
    }
}
