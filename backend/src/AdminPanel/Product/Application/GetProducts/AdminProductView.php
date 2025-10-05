<?php

declare(strict_types=1);

namespace App\AdminPanel\Product\Application\GetProducts;

final class AdminProductView
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?int $priceCents,
        public readonly string $status,
        public readonly ?int $recommendedSlotQuantity,
    ) {
    }
}
