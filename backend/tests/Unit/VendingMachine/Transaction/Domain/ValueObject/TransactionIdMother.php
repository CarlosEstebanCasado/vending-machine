<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Transaction\Domain\ValueObject;

use App\VendingMachine\Transaction\Domain\ValueObject\TransactionId;

final class TransactionIdMother
{
    public static function random(): TransactionId
    {
        return TransactionId::fromString(uniqid('txn-', true));
    }
}
