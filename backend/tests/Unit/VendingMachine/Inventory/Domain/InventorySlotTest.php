<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Domain;

use App\Tests\Unit\VendingMachine\Product\Domain\ValueObject\ProductIdMother;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use DomainException;
use PHPUnit\Framework\TestCase;

final class InventorySlotTest extends TestCase
{
    public function testRestockIncreasesQuantityWithinCapacity(): void
    {
        $capacity = SlotCapacity::fromInt(5);
        $quantity = SlotQuantity::fromInt(2);
        $slot = InventorySlotMother::random(capacity: $capacity, quantity: $quantity, status: SlotStatus::Available);

        $slot->restock(2);

        self::assertSame(4, $slot->quantity()->value());
    }

    public function testRestockBeyondCapacityThrowsException(): void
    {
        $capacity = SlotCapacity::fromInt(4);
        $quantity = SlotQuantity::fromInt(3);
        $slot = InventorySlotMother::random(capacity: $capacity, quantity: $quantity, status: SlotStatus::Available);

        $this->expectException(DomainException::class);
        $slot->restock(2);
    }

    public function testDispenseReducesQuantityWhenAvailable(): void
    {
        $capacity = SlotCapacity::fromInt(6);
        $quantity = SlotQuantity::fromInt(3);
        $slot = InventorySlotMother::random(capacity: $capacity, quantity: $quantity, status: SlotStatus::Available);

        $slot->dispense();

        self::assertSame(2, $slot->quantity()->value());
    }

    public function testDispenseWhenNotAvailableThrowsException(): void
    {
        $slot = InventorySlotMother::random(status: SlotStatus::Disabled, quantity: SlotQuantity::fromInt(2));

        $this->expectException(DomainException::class);
        $slot->dispense();
    }

    public function testAssignDifferentProductWithStockThrowsException(): void
    {
        $productId = ProductIdMother::random();
        $slot = InventorySlotMother::random(productId: $productId, quantity: SlotQuantity::fromInt(2));

        $this->expectException(DomainException::class);
        $slot->assignProduct(ProductIdMother::random());
    }

    public function testClearProductWithStockThrowsException(): void
    {
        $slot = InventorySlotMother::random(productId: ProductIdMother::random(), quantity: SlotQuantity::fromInt(1));

        $this->expectException(DomainException::class);
        $slot->clearProduct();
    }

    public function testNeedsRestockWhenQuantityBelowOrEqualThreshold(): void
    {
        $capacity = SlotCapacity::fromInt(10);
        $threshold = RestockThreshold::fromInt(3);
        $slot = InventorySlotMother::random(capacity: $capacity, quantity: SlotQuantity::fromInt(3), restockThreshold: $threshold);

        self::assertTrue($slot->needsRestock());

        $slot->restock(2);

        self::assertFalse($slot->needsRestock());
    }

    public function testDisableAndEnableSwitchesStatus(): void
    {
        $slot = InventorySlotMother::random(status: SlotStatus::Available);

        $slot->disable();
        self::assertTrue($slot->status()->isDisabled());

        $slot->enable();
        self::assertTrue($slot->status()->isAvailable());
    }
}
