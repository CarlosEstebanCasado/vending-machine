<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject;

use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;

final class SlotCapacityMother
{
    public static function random(): SlotCapacity
    {
        return SlotCapacity::fromInt(random_int(5, 15));
    }
}
