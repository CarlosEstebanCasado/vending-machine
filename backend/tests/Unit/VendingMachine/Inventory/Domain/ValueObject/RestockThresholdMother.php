<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject;

use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;

final class RestockThresholdMother
{
    public static function random(int $max = 3): RestockThreshold
    {
        return RestockThreshold::fromInt(random_int(0, max(0, $max)));
    }
}
