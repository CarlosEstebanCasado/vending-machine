<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject;

use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;

final class SlotStatusMother
{
    public static function random(): SlotStatus
    {
        $statuses = SlotStatus::cases();

        return $statuses[array_rand($statuses)];
    }
}
