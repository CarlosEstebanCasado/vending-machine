<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Product\Domain\ValueObject;

use App\VendingMachine\Product\Domain\ValueObject\ProductName;

final class ProductNameMother
{
    public static function random(string $value = 'Sample Product'): ProductName
    {
        return ProductName::fromString($value);
    }
}
