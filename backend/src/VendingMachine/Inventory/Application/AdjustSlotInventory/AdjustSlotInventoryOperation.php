<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Application\AdjustSlotInventory;

use InvalidArgumentException;

enum AdjustSlotInventoryOperation: string
{
    case Restock = 'restock';
    case Withdraw = 'withdraw';

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'restock' => self::Restock,
            'withdraw' => self::Withdraw,
            default => throw new InvalidArgumentException(sprintf('Unsupported operation "%s".', $value)),
        };
    }
}
