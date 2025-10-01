<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Transaction\Domain;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundleMother;
use App\Tests\Unit\VendingMachine\Session\Domain\ValueObject\VendingSessionIdMother;
use App\Tests\Unit\VendingMachine\Transaction\Domain\ValueObject\AdminUserIdMother;
use App\Tests\Unit\VendingMachine\Transaction\Domain\ValueObject\TransactionIdMother;
use App\VendingMachine\Transaction\Domain\Transaction;
use App\VendingMachine\Transaction\Domain\TransactionItem;
use App\VendingMachine\Transaction\Domain\ValueObject\AdminUserId;
use App\VendingMachine\Transaction\Domain\ValueObject\TransactionStatus;
use App\VendingMachine\Transaction\Domain\ValueObject\TransactionType;
use DateTimeImmutable;

final class TransactionMother
{
    /**
     * @param TransactionItem[] $items
     */
    public static function vend(?array $items = null, ?Money $totalPaid = null, ?Money $changeAmount = null): Transaction
    {
        $items = $items ?? [TransactionItemMother::create(quantity: 1, unitPrice: Money::fromCents(150))];
        $subtotal = self::subtotal($items);
        $totalPaid = $totalPaid ?? $subtotal->add(Money::fromCents(50));
        $changeAmount = $changeAmount ?? Money::fromCents($totalPaid->amountInCents() - $subtotal->amountInCents());

        return Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Vend,
            $items,
            $totalPaid,
            CoinBundleMother::changeOnly(
                quarters: intdiv($changeAmount->amountInCents(), 25),
                dimes: 0,
                nickels: 0
            ),
            VendingSessionIdMother::random()
        );
    }

    /**
     * @param TransactionItem[] $items
     */
    public static function restock(?array $items = null, ?AdminUserId $admin = null): Transaction
    {
        $items = $items ?? [TransactionItemMother::create(quantity: 10, unitPrice: Money::fromCents(100))];
        $subtotal = self::subtotal($items);

        return Transaction::record(
            TransactionIdMother::random(),
            TransactionType::Restock,
            $items,
            Money::fromCents(0),
            CoinBundleMother::empty(),
            sessionId: null,
            adminUserId: $admin ?? AdminUserIdMother::random(),
            metadata: [],
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param TransactionItem[] $items
     */
    private static function subtotal(array $items): Money
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->subtotal()->amountInCents();
        }

        return Money::fromCents($subtotal);
    }
}
