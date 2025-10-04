<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Domain;

use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;

interface InventorySlotRepository
{
    public function find(InventorySlotId $id): ?InventorySlot;

    public function findByMachineAndCode(string $machineId, SlotCode $code): ?InventorySlot;

    /**
     * @return InventorySlot[]
     */
    public function findByMachine(string $machineId): array;

    public function save(InventorySlot $slot, string $machineId): void;
}
