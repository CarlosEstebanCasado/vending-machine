<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Transaction\Domain;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundleMother;
use App\Tests\Unit\VendingMachine\Session\Domain\ValueObject\VendingSessionIdMother;
use App\Tests\Unit\VendingMachine\Transaction\Domain\ValueObject\AdminUserIdMother;
use App\Tests\Unit\VendingMachine\Transaction\Domain\ValueObject\TransactionIdMother;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionId;
use App\VendingMachine\Transaction\Domain\Transaction;
use App\VendingMachine\Transaction\Domain\TransactionItem;
use App\VendingMachine\Transaction\Domain\ValueObject\AdminUserId;
use App\VendingMachine\Transaction\Domain\ValueObject\TransactionStatus;
use App\VendingMachine\Transaction\Domain\ValueObject\TransactionType;
use DomainException;
use PHPUnit\Framework\TestCase;

final class TransactionTest extends TestCase
{
    public function testVendTransactionRequiresChangeConsistency(): void
    {
        $item = TransactionItemMother::create(quantity: 1, unitPrice: Money::fromCents(150));
        $totalPaid = Money::fromCents(200);
        $change = CoinBundleMother::fromArray([25 => 2]);

        $transaction = Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Vend,
            [$item],
            $totalPaid,
            $change,
            VendingSessionIdMother::random()
        );

        self::assertSame(TransactionStatus::Completed, $transaction->status());
        self::assertSame(1, count($transaction->items()));
        self::assertEquals($change->toArray(), $transaction->changeDispensed()->toArray());
    }

    public function testVendTransactionThrowsWhenChangeMismatch(): void
    {
        $item = TransactionItemMother::create(quantity: 1, unitPrice: Money::fromCents(150));
        $totalPaid = Money::fromCents(200);
        $change = CoinBundleMother::fromArray([25 => 1]);

        $this->expectException(DomainException::class);
        Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Vend,
            [$item],
            $totalPaid,
            $change,
            VendingSessionIdMother::random()
        );
    }

    public function testVendTransactionThrowsWhenNoSession(): void
    {
        $item = TransactionItemMother::create();

        $this->expectException(DomainException::class);
        Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Vend,
            [$item],
            Money::fromCents(100),
            CoinBundleMother::empty(),
            null
        );
    }

    public function testRestockRequiresAdmin(): void
    {
        $item = TransactionItemMother::create(quantity: 5, unitPrice: Money::fromCents(100));

        $this->expectException(DomainException::class);
        Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Restock,
            [$item],
            Money::fromCents(0),
            CoinBundleMother::empty(),
            sessionId: null,
            adminUserId: null
        );
    }

    public function testRestockCannotReturnChange(): void
    {
        $item = TransactionItemMother::create(quantity: 5, unitPrice: Money::fromCents(100));

        $this->expectException(DomainException::class);
        Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Restock,
            [$item],
            Money::fromCents(0),
            CoinBundleMother::fromArray([25 => 1]),
            sessionId: null,
            adminUserId: AdminUserIdMother::random()
        );
    }

    public function testReturnTransactionsRequireSession(): void
    {
        $this->expectException(DomainException::class);
        Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Return,
            [],
            Money::fromCents(0),
            CoinBundleMother::changeOnly(quarters: 1),
            sessionId: null
        );
    }

    public function testReturnTransactionsRequireChangeDispensed(): void
    {
        $this->expectException(DomainException::class);
        Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Return,
            [],
            Money::fromCents(0),
            CoinBundleMother::empty(),
            sessionId: VendingSessionIdMother::random()
        );
    }

    public function testReturnTransactionsCannotRecordPayments(): void
    {
        $this->expectException(DomainException::class);
        Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Return,
            [],
            Money::fromCents(100),
            CoinBundleMother::changeOnly(quarters: 4),
            sessionId: VendingSessionIdMother::random()
        );
    }

    public function testMarkFailedSetsStatusAndReason(): void
    {
        $transaction = TransactionMother::vend();

        $transaction->markFailed('dispense_error');

        self::assertSame(TransactionStatus::Failed, $transaction->status());
        self::assertSame('dispense_error', $transaction->failureReason());

        $transaction->markCompleted();
        self::assertSame(TransactionStatus::Completed, $transaction->status());
        self::assertNull($transaction->failureReason());
    }

    public function testRestoreWithInconsistentStateThrows(): void
    {
        $item = TransactionItemMother::create(quantity: 1, unitPrice: Money::fromCents(100));

        $this->expectException(DomainException::class);

        Transaction::restore(
            TransactionIdMother::random(),
            TransactionType::Vend,
            TransactionStatus::Completed,
            [$item],
            Money::fromCents(100),
            CoinBundleMother::empty(),
            sessionId: null,
            adminUserId: null,
            metadata: [],
            createdAt: new \DateTimeImmutable(),
            failureReason: null,
        );
    }

    public function testAddMetadataStoresValues(): void
    {
        $transaction = TransactionMother::vend();
        $transaction->addMetadata('machine', '11');

        self::assertSame('11', $transaction->metadata()['machine']);
    }
}
