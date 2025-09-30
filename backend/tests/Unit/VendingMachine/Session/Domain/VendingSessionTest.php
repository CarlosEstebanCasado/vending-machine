<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Domain;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundleMother;
use App\Tests\Unit\VendingMachine\Product\Domain\ValueObject\ProductIdMother;
use App\Tests\Unit\VendingMachine\Session\Domain\ValueObject\VendingSessionIdMother;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use App\VendingMachine\Session\Domain\ValueObject\SessionCloseReason;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use App\VendingMachine\Session\Domain\VendingSession;
use DomainException;
use PHPUnit\Framework\TestCase;

final class VendingSessionTest extends TestCase
{
    public function testInsertCoinUpdatesBalance(): void
    {
        $session = VendingSessionMother::start();

        $session->insertCoin(CoinDenomination::OneDollar);
        $session->insertCoin(CoinDenomination::TwentyFiveCents);

        self::assertSame(125, $session->balance()->amountInCents());
        self::assertSame(1, $session->insertedCoins()->quantityFor(CoinDenomination::OneDollar)->value());
        self::assertSame(1, $session->insertedCoins()->quantityFor(CoinDenomination::TwentyFiveCents)->value());
    }

    public function testApprovePurchaseTransitionsToReady(): void
    {
        $session = VendingSessionMother::start();
        $session->insertCoin(CoinDenomination::OneDollar);
        $session->selectProduct(ProductIdMother::random());

        $change = CoinBundleMother::changeOnly(quarters: 1, dimes: 1, nickels: 1);
        $session->approvePurchase(Money::fromCents(60), $change);

        self::assertSame('ready', $session->state()->value);
        self::assertNotNull($session->selectedProductId());
    }

    public function testApprovePurchaseFailsWithInsufficientBalance(): void
    {
        $session = VendingSessionMother::start();
        $session->insertCoin(CoinDenomination::TenCents);
        $session->selectProduct(ProductIdMother::random());

        $this->expectException(DomainException::class);
        $session->approvePurchase(Money::fromCents(100), CoinBundleMother::empty());
    }

    public function testApprovePurchaseFailsWithMismatchedChange(): void
    {
        $session = VendingSessionMother::start();
        $session->insertCoin(CoinDenomination::OneDollar);
        $session->selectProduct(ProductIdMother::random());

        $change = CoinBundleMother::changeOnly(quarters: 1);

        $this->expectException(DomainException::class);
        $session->approvePurchase(Money::fromCents(10), $change);
    }

    public function testStartDispensingReturnsChangeAndClosesSession(): void
    {
        $session = VendingSessionMother::start();
        $session->insertCoin(CoinDenomination::OneDollar);
        $session->selectProduct(ProductIdMother::random());
        $changePlan = CoinBundleMother::changeOnly(quarters: 1, nickels: 1);
        $session->approvePurchase(Money::fromCents(70), $changePlan);

        $change = $session->startDispensing();

        self::assertSame('dispensing', $session->state()->value);
        self::assertTrue($session->isClosed());
        self::assertSame(SessionCloseReason::Completed, $session->closeReason());
        self::assertSame($changePlan->toArray(), $change->toArray());
        self::assertSame(0, $session->balance()->amountInCents());
        self::assertNull($session->selectedProductId());
    }

    public function testCancelReturnsInsertedCoinsAndChangePlan(): void
    {
        $session = VendingSessionMother::start();
        $session->insertCoin(CoinDenomination::OneDollar);
        $session->selectProduct(ProductIdMother::random());
        $changePlan = CoinBundleMother::changeOnly(quarters: 1, nickels: 1);
        $session->approvePurchase(Money::fromCents(70), $changePlan);

        $closure = $session->cancel();

        self::assertSame('cancelled', $session->state()->value);
        self::assertTrue($session->isClosed());
        self::assertSame(SessionCloseReason::Cancelled, $session->closeReason());
        self::assertSame([100 => 1], $closure->insertedCoins()->toArray());
        self::assertSame($changePlan->toArray(), $closure->changePlan()?->toArray());
        self::assertSame(0, $session->balance()->amountInCents());
        self::assertNull($session->selectedProductId());
    }

    public function testTimeoutReturnsInsertedCoins(): void
    {
        $session = VendingSessionMother::start();
        $session->insertCoin(CoinDenomination::TwentyFiveCents);

        $closure = $session->timeout();

        self::assertSame('timeout', $session->state()->value);
        self::assertTrue($session->isClosed());
        self::assertSame(SessionCloseReason::Timeout, $session->closeReason());
        self::assertSame([25 => 1], $closure->insertedCoins()->toArray());
        self::assertNull($closure->changePlan());
        self::assertSame(0, $session->balance()->amountInCents());
        self::assertNull($session->selectedProductId());
    }

    public function testRestoreReadyWithoutChangePlanFails(): void
    {
        $this->expectException(DomainException::class);

        VendingSession::restore(
            VendingSessionIdMother::random(),
            VendingSessionState::Ready,
            CoinBundleMother::fromArray([100 => 1]),
            Money::fromCents(100),
            ProductIdMother::random(),
            null,
            null,
        );
    }

    public function testRestoreReadyWithoutSelectionFails(): void
    {
        $this->expectException(DomainException::class);

        VendingSession::restore(
            VendingSessionIdMother::random(),
            VendingSessionState::Ready,
            CoinBundleMother::fromArray([100 => 1]),
            Money::fromCents(100),
            null,
            CoinBundleMother::empty(),
            null,
        );
    }

    public function testRestoreCancelledWithRemainingCoinsFails(): void
    {
        $this->expectException(DomainException::class);

        VendingSession::restore(
            VendingSessionIdMother::random(),
            VendingSessionState::Cancelled,
            CoinBundleMother::fromArray([25 => 1]),
            Money::fromCents(0),
            null,
            null,
            SessionCloseReason::Cancelled,
        );
    }

    public function testRestoreDispensingWithChangePlanFails(): void
    {
        $this->expectException(DomainException::class);

        VendingSession::restore(
            VendingSessionIdMother::random(),
            VendingSessionState::Dispensing,
            CoinBundleMother::empty(),
            Money::fromCents(0),
            null,
            CoinBundleMother::changeOnly(quarters: 1),
            SessionCloseReason::Completed,
        );
    }

    public function testCannotInsertCoinWhenNotCollecting(): void
    {
        $session = VendingSessionMother::start();
        $session->insertCoin(CoinDenomination::OneDollar);
        $session->selectProduct(ProductIdMother::random());
        $session->approvePurchase(Money::fromCents(100), CoinBundleMother::empty());

        $this->expectException(DomainException::class);
        $session->insertCoin(CoinDenomination::FiveCents);
    }
}
