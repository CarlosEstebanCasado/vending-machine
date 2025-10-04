<?php

declare(strict_types=1);

namespace App\AdminPanel\Inventory\Application\GetSlots;

final readonly class AdminSlotsInventoryResult
{
    /**
     * @param AdminSlotInventoryView[] $slots
     */
    public function __construct(
        public string $machineId,
        public array $slots,
    ) {
    }
}
