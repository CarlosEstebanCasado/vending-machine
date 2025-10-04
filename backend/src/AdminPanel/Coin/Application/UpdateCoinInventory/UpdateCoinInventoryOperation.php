<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\Application\UpdateCoinInventory;

use InvalidArgumentException;

enum UpdateCoinInventoryOperation: string
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
