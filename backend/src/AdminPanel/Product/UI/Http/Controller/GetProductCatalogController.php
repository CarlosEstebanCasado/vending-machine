<?php

declare(strict_types=1);

namespace App\AdminPanel\Product\UI\Http\Controller;

use App\AdminPanel\Product\Application\GetProducts\AdminGetProductsQuery;
use App\AdminPanel\Product\Application\GetProducts\AdminGetProductsQueryHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/products', name: 'admin_products_catalog', methods: ['GET'])]
final class GetProductCatalogController
{
    public function __construct(private readonly AdminGetProductsQueryHandler $handler)
    {
    }

    public function __invoke(): JsonResponse
    {
        $result = $this->handler->handle(new AdminGetProductsQuery());

        return new JsonResponse([
            'products' => array_map(
                static fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'priceCents' => $product->priceCents,
                    'status' => $product->status,
                    'recommendedSlotQuantity' => $product->recommendedSlotQuantity,
                ],
                $result->products,
            ),
        ]);
    }
}
