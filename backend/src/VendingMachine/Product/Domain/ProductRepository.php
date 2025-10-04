<?php

declare(strict_types=1);

namespace App\VendingMachine\Product\Domain;

use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Product\Domain\ValueObject\ProductSku;

interface ProductRepository
{
    public function find(ProductId $id): ?Product;

    public function findBySku(ProductSku $sku): ?Product;

    /**
     * @return Product[]
     */
    public function all(): array;

    public function save(Product $product): void;
}
