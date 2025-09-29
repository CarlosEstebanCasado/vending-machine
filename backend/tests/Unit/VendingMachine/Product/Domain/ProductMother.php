<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Product\Domain;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\Shared\Money\Domain\MoneyMother;
use App\VendingMachine\Product\Domain\Product;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Product\Domain\ValueObject\ProductName;
use App\VendingMachine\Product\Domain\ValueObject\ProductSku;
use App\VendingMachine\Product\Domain\ValueObject\ProductStatus;
use App\VendingMachine\Product\Domain\ValueObject\RecommendedSlotQuantity;

final class ProductMother
{
    public static function random(
        ?ProductId $id = null,
        ?ProductSku $sku = null,
        ?ProductName $name = null,
        ?Money $price = null,
        ?ProductStatus $status = null,
        ?RecommendedSlotQuantity $recommendedSlotQuantity = null,
    ): Product {
        return Product::create(
            $id ?? ProductIdMother::random(),
            $sku ?? ProductSkuMother::random(),
            $name ?? ProductNameMother::random(),
            $price ?? MoneyMother::fromCents(100),
            $status,
            $recommendedSlotQuantity ?? RecommendedSlotQuantity::fromInt(0)
        );
    }
}
