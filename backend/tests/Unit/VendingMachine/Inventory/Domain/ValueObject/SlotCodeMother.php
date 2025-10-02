<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject;

use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;

final class SlotCodeMother
{
    private const CODES = ['11', '12', '21', '22'];

    public static function random(): SlotCode
    {
        return SlotCode::fromString(self::CODES[array_rand(self::CODES)]);
    }
}
