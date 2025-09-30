<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\CoinInventory\Domain\ValueObject;

use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;

final class CoinBundleMother
{
    /**
     * @param array<int, int> $coins
     */
    public static function fromArray(array $coins): CoinBundle
    {
        return CoinBundle::fromArray($coins);
    }

    public static function empty(): CoinBundle
    {
        return CoinBundle::empty();
    }

    public static function changeOnly(int $quarters = 0, int $dimes = 0, int $nickels = 0): CoinBundle
    {
        $bundle = CoinBundle::fromArray([
            25 => $quarters,
            10 => $dimes,
            5 => $nickels,
        ]);

        $bundle->assertContainsOnlyChangeCoins();

        return $bundle;
    }
}
