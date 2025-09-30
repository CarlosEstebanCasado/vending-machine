<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Domain;

use App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject\InventorySlotIdMother;
use App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject\RestockThresholdMother;
use App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject\SlotCapacityMother;
use App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject\SlotCodeMother;
use App\Tests\Unit\VendingMachine\Inventory\Domain\ValueObject\SlotQuantityMother;
use App\VendingMachine\Inventory\Domain\InventorySlot;
use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;

final class InventorySlotMother
{
    public static function random(
        ?InventorySlotId $id = null,
        ?SlotCode $code = null,
        ?SlotCapacity $capacity = null,
        ?SlotQuantity $quantity = null,
        ?RestockThreshold $restockThreshold = null,
        ?SlotStatus $status = null,
        ?ProductId $productId = null,
    ): InventorySlot {
        $capacity = $capacity ?? SlotCapacityMother::random();
        $quantity = $quantity ?? SlotQuantityMother::random($capacity->value());
        $restockThreshold = $restockThreshold ?? RestockThresholdMother::random($capacity->value());

        if ($restockThreshold->value() > $capacity->value()) {
            $restockThreshold = RestockThreshold::fromInt($capacity->value());
        }

        if ($quantity->value() > $capacity->value()) {
            $quantity = SlotQuantity::fromInt($capacity->value());
        }

        return InventorySlot::create(
            $id ?? InventorySlotIdMother::random(),
            $code ?? SlotCodeMother::random(),
            $capacity,
            $quantity,
            $restockThreshold,
            $status ?? SlotStatus::Available,
            $productId,
        );
    }
}
