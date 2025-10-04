<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Application\AdjustInventory;

use InvalidArgumentException;

enum AdjustCoinInventoryOperation: string
{
    case Deposit = 'deposit';
    case Withdraw = 'withdraw';

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'deposit' => self::Deposit,
            'withdraw' => self::Withdraw,
            default => throw new InvalidArgumentException(sprintf('Unsupported operation "%s".', $value)),
        };
    }
}
