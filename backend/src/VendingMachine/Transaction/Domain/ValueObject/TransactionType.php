<?php

declare(strict_types=1);

namespace App\VendingMachine\Transaction\Domain\ValueObject;

enum TransactionType: string
{
    case Vend = 'vend';
    case Return = 'return';
    case Restock = 'restock';
    case Adjustment = 'adjustment';
}
