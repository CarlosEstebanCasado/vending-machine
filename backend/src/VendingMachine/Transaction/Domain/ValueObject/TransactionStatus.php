<?php

declare(strict_types=1);

namespace App\VendingMachine\Transaction\Domain\ValueObject;

enum TransactionStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';

    public function isCompleted(): bool
    {
        return self::Completed === $this;
    }

    public function isFailed(): bool
    {
        return self::Failed === $this;
    }
}
