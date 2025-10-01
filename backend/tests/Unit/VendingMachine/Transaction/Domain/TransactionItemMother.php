<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Transaction\Domain;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\Shared\Money\Domain\MoneyMother;
use App\Tests\Unit\VendingMachine\Product\Domain\ValueObject\ProductIdMother;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Transaction\Domain\TransactionItem;

final class TransactionItemMother
{
    public static function create(
        ?ProductId $productId = null,
        int $quantity = 1,
        ?Money $unitPrice = null,
    ): TransactionItem {
        return TransactionItem::create(
            $productId ?? ProductIdMother::random(),
            $quantity,
            $unitPrice ?? MoneyMother::fromCents(150),
        );
    }
}
