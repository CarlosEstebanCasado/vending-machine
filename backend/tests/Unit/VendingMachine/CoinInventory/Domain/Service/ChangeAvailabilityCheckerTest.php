<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\CoinInventory\Domain\Service;

use App\VendingMachine\CoinInventory\Domain\CoinInventory;
use App\VendingMachine\CoinInventory\Domain\Service\ChangeAvailabilityChecker;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use PHPUnit\Framework\TestCase;

final class ChangeAvailabilityCheckerTest extends TestCase
{
    private ChangeAvailabilityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new ChangeAvailabilityChecker();
    }

    public function testItReportsChangeAsSufficientWhenEnoughCoinsAreAvailable(): void
    {
        $inventory = CoinInventory::restore(
            CoinBundle::fromArray([
                25 => 4,
                10 => 5,
                5 => 5,
            ]),
            CoinBundle::empty()
        );

        self::assertTrue($this->checker->isChangeSufficient($inventory));
    }

    public function testItReportsChangeAsInsufficientWhenNoChangeCoinsExist(): void
    {
        $inventory = CoinInventory::restore(
            CoinBundle::fromArray([
                100 => 10,
            ]),
            CoinBundle::empty()
        );

        self::assertFalse($this->checker->isChangeSufficient($inventory));
    }

    public function testItReportsChangeAsInsufficientWhenExactAmountsCannotBeMade(): void
    {
        $inventory = CoinInventory::restore(
            CoinBundle::fromArray([
                10 => 10,
            ]),
            CoinBundle::empty()
        );

        self::assertFalse($this->checker->isChangeSufficient($inventory));
    }
}
