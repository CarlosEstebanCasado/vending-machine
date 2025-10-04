<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Application\GetSlots;

use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Product\Domain\Product;
use App\VendingMachine\Product\Domain\ProductRepository;

final class AdminGetSlotsQueryHandler
{
    public function __construct(
        private readonly InventorySlotRepository $slotRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function __invoke(AdminGetSlotsQuery $query): AdminSlotsInventoryResult
    {
        $slots = $this->slotRepository->findByMachine($query->machineId);
        $productMap = $this->buildProductMap();

        $slotViews = [];

        foreach ($slots as $slot) {
            $product = null;
            if (null !== $slot->productId()) {
                $product = $productMap[$slot->productId()->value()] ?? null;
            }

            $slotViews[] = new AdminSlotInventoryView(
                slotCode: $slot->code()->value(),
                status: $slot->status()->value,
                capacity: $slot->capacity()->value(),
                quantity: $slot->quantity()->value(),
                restockThreshold: $slot->restockThreshold()->value(),
                needsRestock: $slot->needsRestock(),
                productId: $slot->productId()?->value(),
                productName: $product?->name()->value(),
                priceCents: $product?->price()->amountInCents(),
                recommendedSlotQuantity: $product?->recommendedSlotQuantity()->value(),
            );
        }

        return new AdminSlotsInventoryResult($query->machineId, $slotViews);
    }

    /**
     * @return array<string, Product>
     */
    private function buildProductMap(): array
    {
        $products = $this->productRepository->all();

        $map = [];

        foreach ($products as $product) {
            $map[$product->id()->value()] = $product;
        }

        return $map;
    }
}
