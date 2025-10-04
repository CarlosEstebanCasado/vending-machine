<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Domain;

use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use DomainException;

final class InventorySlot
{
    private function __construct(
        private readonly InventorySlotId $id,
        private SlotCode $code,
        private SlotCapacity $capacity,
        private SlotQuantity $quantity,
        private RestockThreshold $restockThreshold,
        private SlotStatus $status,
        private ?ProductId $productId,
    ) {
        $this->ensureThresholdNotGreaterThanCapacity($restockThreshold, $capacity);
        $this->ensureQuantityNotGreaterThanCapacity($quantity, $capacity);
    }

    public static function create(
        InventorySlotId $id,
        SlotCode $code,
        SlotCapacity $capacity,
        ?SlotQuantity $quantity = null,
        ?RestockThreshold $restockThreshold = null,
        ?SlotStatus $status = null,
        ?ProductId $productId = null,
    ): self {
        return new self(
            id: $id,
            code: $code,
            capacity: $capacity,
            quantity: $quantity ?? SlotQuantity::fromInt(0),
            restockThreshold: $restockThreshold ?? RestockThreshold::fromInt(0),
            status: $status ?? SlotStatus::Available,
            productId: $productId,
        );
    }

    public static function restore(
        InventorySlotId $id,
        SlotCode $code,
        SlotCapacity $capacity,
        SlotQuantity $quantity,
        RestockThreshold $restockThreshold,
        SlotStatus $status,
        ?ProductId $productId,
    ): self {
        return new self(
            id: $id,
            code: $code,
            capacity: $capacity,
            quantity: $quantity,
            restockThreshold: $restockThreshold,
            status: $status,
            productId: $productId,
        );
    }

    public function id(): InventorySlotId
    {
        return $this->id;
    }

    public function code(): SlotCode
    {
        return $this->code;
    }

    public function capacity(): SlotCapacity
    {
        return $this->capacity;
    }

    public function quantity(): SlotQuantity
    {
        return $this->quantity;
    }

    public function restockThreshold(): RestockThreshold
    {
        return $this->restockThreshold;
    }

    public function status(): SlotStatus
    {
        return $this->status;
    }

    public function productId(): ?ProductId
    {
        return $this->productId;
    }

    public function hasProductAssigned(): bool
    {
        return null !== $this->productId;
    }

    public function assignProduct(ProductId $productId): void
    {
        if (null !== $this->productId && !$this->productId->equals($productId) && !$this->quantity->isZero()) {
            throw new DomainException('Cannot assign a different product while the slot still holds stock.');
        }

        $this->productId = $productId;
    }

    public function clearProduct(): void
    {
        if (!$this->quantity->isZero()) {
            throw new DomainException('Cannot clear product while slot has stock.');
        }

        $this->productId = null;
    }

    public function restock(int $units): void
    {
        $newQuantity = $this->quantity->add($units);
        $this->ensureQuantityNotGreaterThanCapacity($newQuantity, $this->capacity);

        $this->quantity = $newQuantity;
        if ($this->status->isDisabled()) {
            $this->status = SlotStatus::Available;
        }
    }

    public function dispense(int $units = 1): void
    {
        if ($units <= 0) {
            throw new DomainException('Units to dispense must be greater than zero.');
        }

        if (!$this->status->isAvailable()) {
            throw new DomainException('Cannot dispense from a slot that is not available.');
        }

        $this->quantity = $this->quantity->subtract($units);
    }

    public function removeStock(int $units): void
    {
        $this->quantity = $this->quantity->subtract($units);

        if ($this->quantity->isZero()) {
            $this->status = SlotStatus::Disabled;
        }
    }

    public function markReserved(): void
    {
        if ($this->status->isDisabled()) {
            throw new DomainException('Cannot reserve a disabled slot.');
        }

        $this->status = SlotStatus::Reserved;
    }

    public function markAvailable(): void
    {
        $this->status = SlotStatus::Available;
    }

    public function disable(): void
    {
        $this->status = SlotStatus::Disabled;
    }

    public function enable(): void
    {
        $this->status = SlotStatus::Available;
    }

    public function needsRestock(): bool
    {
        return $this->quantity->value() <= $this->restockThreshold->value();
    }

    public function updateRestockThreshold(RestockThreshold $threshold): void
    {
        $this->ensureThresholdNotGreaterThanCapacity($threshold, $this->capacity);
        $this->restockThreshold = $threshold;
    }

    public function rename(SlotCode $code): void
    {
        $this->code = $code;
    }

    public function adjustCapacity(SlotCapacity $capacity): void
    {
        $this->ensureQuantityNotGreaterThanCapacity($this->quantity, $capacity);
        $this->ensureThresholdNotGreaterThanCapacity($this->restockThreshold, $capacity);
        $this->capacity = $capacity;
    }

    private function ensureQuantityNotGreaterThanCapacity(SlotQuantity $quantity, SlotCapacity $capacity): void
    {
        if ($quantity->value() > $capacity->value()) {
            throw new DomainException('Slot quantity cannot exceed its capacity.');
        }
    }

    private function ensureThresholdNotGreaterThanCapacity(RestockThreshold $threshold, SlotCapacity $capacity): void
    {
        if ($threshold->value() > $capacity->value()) {
            throw new DomainException('Restock threshold cannot exceed slot capacity.');
        }
    }
}
