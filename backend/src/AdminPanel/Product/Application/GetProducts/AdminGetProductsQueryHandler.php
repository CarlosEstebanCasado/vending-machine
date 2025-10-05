<?php

declare(strict_types=1);

namespace App\AdminPanel\Product\Application\GetProducts;

use App\VendingMachine\Product\Domain\ProductRepository;

final class AdminGetProductsQueryHandler
{
    public function __construct(private readonly ProductRepository $repository)
    {
    }

    public function handle(AdminGetProductsQuery $query): AdminProductsResult
    {
        $products = array_map(
            static fn ($product) => new AdminProductView(
                id: $product->id()->value(),
                name: $product->name()->value(),
                priceCents: $product->price()->amountInCents(),
                status: $product->status()->value,
                recommendedSlotQuantity: $product->recommendedSlotQuantity()->value(),
            ),
            array_filter(
                $this->repository->all(),
                static fn ($product) => $product->status()->isActive(),
            ),
        );

        return new AdminProductsResult(array_values($products));
    }
}
