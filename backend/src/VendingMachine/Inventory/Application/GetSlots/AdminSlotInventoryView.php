<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Application\GetSlots;

final readonly class AdminSlotInventoryView
{
    public function __construct(
        public string $slotCode,
        public string $status,
        public int $capacity,
        public int $quantity,
        public int $restockThreshold,
        public bool $needsRestock,
        public ?string $productId,
        public ?string $productName,
        public ?int $priceCents,
        public ?int $recommendedSlotQuantity,
    ) {
    }
}
