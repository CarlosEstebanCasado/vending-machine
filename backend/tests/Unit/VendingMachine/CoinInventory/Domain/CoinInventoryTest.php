<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\CoinInventory\Domain;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundleMother;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use DomainException;
use PHPUnit\Framework\TestCase;

final class CoinInventoryTest extends TestCase
{
    public function testDepositIncreasesAvailableCoins(): void
    {
        $inventory = CoinInventoryMother::empty();
        $inventory->deposit(CoinBundleMother::fromArray([
            100 => 2,
            25 => 4,
        ]));

        $available = $inventory->availableCoins();

        self::assertSame(2, $available->quantityFor(CoinDenomination::OneDollar)->value());
        self::assertSame(4, $available->quantityFor(CoinDenomination::TwentyFiveCents)->value());
    }

    public function testReserveChangeMovesCoinsFromAvailableToReserved(): void
    {
        $inventory = CoinInventoryMother::withAvailable([
            25 => 5,
            10 => 3,
            5 => 4,
        ]);

        $bundle = CoinBundleMother::changeOnly(quarters: 2, dimes: 1, nickels: 1);

        $inventory->reserveChange($bundle);

        self::assertSame(3, $inventory->availableCoins()->quantityFor(CoinDenomination::TwentyFiveCents)->value());
        self::assertSame(2, $inventory->availableCoins()->quantityFor(CoinDenomination::TenCents)->value());
        self::assertSame(3, $inventory->availableCoins()->quantityFor(CoinDenomination::FiveCents)->value());

        self::assertSame(2, $inventory->reservedCoins()->quantityFor(CoinDenomination::TwentyFiveCents)->value());
        self::assertSame(1, $inventory->reservedCoins()->quantityFor(CoinDenomination::TenCents)->value());
        self::assertSame(1, $inventory->reservedCoins()->quantityFor(CoinDenomination::FiveCents)->value());
    }

    public function testReserveChangeThrowsWhenInsufficientCoins(): void
    {
        $inventory = CoinInventoryMother::withAvailable([
            25 => 1,
            10 => 1,
            5 => 0,
        ]);

        $this->expectException(DomainException::class);
        $inventory->reserveChange(CoinBundleMother::changeOnly(quarters: 2));
    }

    public function testReleaseReservedReturnsCoinsToAvailable(): void
    {
        $inventory = CoinInventoryMother::withAvailableAndReserved(
            available: [25 => 3, 10 => 2, 5 => 1],
            reserved: [25 => 1, 10 => 1]
        );

        $bundle = CoinBundleMother::changeOnly(quarters: 1, dimes: 1);

        $inventory->releaseReserved($bundle);

        self::assertSame(4, $inventory->availableCoins()->quantityFor(CoinDenomination::TwentyFiveCents)->value());
        self::assertSame(3, $inventory->availableCoins()->quantityFor(CoinDenomination::TenCents)->value());
        self::assertSame(0, $inventory->reservedCoins()->quantityFor(CoinDenomination::TwentyFiveCents)->value());
        self::assertSame(0, $inventory->reservedCoins()->quantityFor(CoinDenomination::TenCents)->value());
    }

    public function testCommitReservedReducesReservedCoins(): void
    {
        $inventory = CoinInventoryMother::withAvailableAndReserved(
            available: [25 => 4, 10 => 4, 5 => 4],
            reserved: [25 => 2, 10 => 1]
        );

        $bundle = CoinBundleMother::changeOnly(quarters: 1, dimes: 1);

        $inventory->commitReserved($bundle);

        self::assertSame(1, $inventory->reservedCoins()->quantityFor(CoinDenomination::TwentyFiveCents)->value());
        self::assertSame(0, $inventory->reservedCoins()->quantityFor(CoinDenomination::TenCents)->value());
    }

    public function testPlanChangeForUsesOnlyAllowedDenominations(): void
    {
        $inventory = CoinInventoryMother::withAvailable([
            100 => 5,
            25 => 4,
            10 => 3,
            5 => 6,
        ]);

        $plan = $inventory->planChangeFor(Money::fromCents(65));

        self::assertSame([
            25 => 2,
            10 => 1,
            5 => 1,
        ], $plan->toArray());
    }

    public function testPlanChangeForThrowsWhenExactChangeNotPossible(): void
    {
        $inventory = CoinInventoryMother::withAvailable([
            25 => 1,
            10 => 0,
            5 => 0,
        ]);

        $this->expectException(DomainException::class);
        $inventory->planChangeFor(Money::fromCents(30));
    }

    public function testPlanChangeIgnoresDollarCoinsEvenIfAvailable(): void
    {
        $inventory = CoinInventoryMother::withAvailable([
            100 => 5,
            25 => 0,
            10 => 0,
            5 => 0,
        ]);

        $this->expectException(DomainException::class);
        $inventory->planChangeFor(Money::fromCents(100));
    }
}
