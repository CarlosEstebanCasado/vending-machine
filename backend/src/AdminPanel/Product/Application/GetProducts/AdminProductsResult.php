<?php

declare(strict_types=1);

namespace App\AdminPanel\Product\Application\GetProducts;

final class AdminProductsResult
{
    /**
     * @param AdminProductView[] $products
     */
    public function __construct(
        public readonly array $products,
    ) {
    }
}
