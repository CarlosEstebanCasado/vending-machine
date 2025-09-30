<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject;

use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;

final class SlotQuantityMother
{
    public static function random(int $max = 5): SlotQuantity
    {
        return SlotQuantity::fromInt(random_int(0, max(0, $max)));
    }
}
