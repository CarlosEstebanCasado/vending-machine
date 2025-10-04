<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Application\AdjustSlotInventory;

final readonly class AdminAdjustSlotInventoryCommand
{
    public function __construct(
        public string $machineId,
        public string $slotCode,
        public AdjustSlotInventoryOperation $operation,
        public int $quantity,
        public ?string $productId,
    ) {
    }
}
