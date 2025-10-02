<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\CoinInventory\Domain;

use App\Tests\Unit\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundleMother;
use App\VendingMachine\CoinInventory\Domain\CoinInventory;

final class CoinInventoryMother
{
    public static function withAvailable(array $coins): CoinInventory
    {
        return CoinInventory::create(CoinBundleMother::fromArray($coins));
    }

    public static function withAvailableAndReserved(array $available, array $reserved): CoinInventory
    {
        return CoinInventory::restore(
            CoinBundleMother::fromArray($available),
            CoinBundleMother::fromArray($reserved)
        );
    }

    public static function empty(): CoinInventory
    {
        return CoinInventory::create();
    }
}
