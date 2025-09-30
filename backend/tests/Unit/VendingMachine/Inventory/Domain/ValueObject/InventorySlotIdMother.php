<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject;

use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;

final class InventorySlotIdMother
{
    public static function random(): InventorySlotId
    {
        return InventorySlotId::fromString(uniqid('slot-', true));
    }
}
