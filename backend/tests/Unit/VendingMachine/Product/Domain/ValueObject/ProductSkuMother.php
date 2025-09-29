<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Product\Domain\ValueObject;

use App\VendingMachine\Product\Domain\ValueObject\ProductSku;

final class ProductSkuMother
{
    public static function random(string $value = 'SKU-001'): ProductSku
    {
        return ProductSku::fromString($value);
    }
}
