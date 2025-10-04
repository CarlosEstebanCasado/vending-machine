<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\CoinInventory\Domain\Service;

use App\Tests\Unit\VendingMachine\CoinInventory\Domain\CoinInventoryMother;
use App\VendingMachine\CoinInventory\Domain\Service\ChangeAvailabilityChecker;
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
        $inventory = CoinInventoryMother::withAvailable([
            25 => 4,
            10 => 5,
            5 => 5,
        ]);

        self::assertTrue($this->checker->isChangeSufficient($inventory));
    }

    public function testItReportsChangeAsInsufficientWhenNoChangeCoinsExist(): void
    {
        $inventory = CoinInventoryMother::withAvailable([
            100 => 10,
        ]);

        self::assertFalse($this->checker->isChangeSufficient($inventory));
    }

    public function testItReportsChangeAsInsufficientWhenExactAmountsCannotBeMade(): void
    {
        $inventory = CoinInventoryMother::withAvailable([
            10 => 10,
        ]);

        self::assertFalse($this->checker->isChangeSufficient($inventory));
    }
}
