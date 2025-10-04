<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Application\GetSlots;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\VendingMachine\Inventory\Domain\InventorySlotMother;
use App\Tests\Unit\VendingMachine\Product\Domain\ProductMother;
use App\VendingMachine\Inventory\Application\GetSlots\AdminGetSlotsQuery;
use App\VendingMachine\Inventory\Application\GetSlots\AdminGetSlotsQueryHandler;
use App\VendingMachine\Inventory\Application\GetSlots\AdminSlotsInventoryResult;
use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Product\Domain\ProductRepository;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Product\Domain\ValueObject\ProductName;
use App\VendingMachine\Product\Domain\ValueObject\ProductSku;
use App\VendingMachine\Product\Domain\ValueObject\ProductStatus;
use App\VendingMachine\Product\Domain\ValueObject\RecommendedSlotQuantity;
use PHPUnit\Framework\TestCase;

final class AdminGetSlotsQueryHandlerTest extends TestCase
{
    public function testMapsSlotsWithProductInformation(): void
    {
        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(4),
            restockThreshold: RestockThreshold::fromInt(2),
            status: SlotStatus::Available,
            productId: ProductId::fromString('product-1'),
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachine')
            ->with('machine-1')
            ->willReturn([$slot]);

        $product = ProductMother::random(
            id: ProductId::fromString('product-1'),
            sku: ProductSku::fromString('SKU-001'),
            name: ProductName::fromString('Water'),
            price: Money::fromCents(65),
            status: ProductStatus::Active,
            recommendedSlotQuantity: RecommendedSlotQuantity::fromInt(8),
        );

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::once())
            ->method('all')
            ->willReturn([$product]);

        $handler = new AdminGetSlotsQueryHandler($slotRepository, $productRepository);

        $result = $handler->handle(new AdminGetSlotsQuery('machine-1'));

        self::assertInstanceOf(AdminSlotsInventoryResult::class, $result);
        self::assertSame('machine-1', $result->machineId);
        self::assertCount(1, $result->slots);
        $slotView = $result->slots[0];
        self::assertSame('11', $slotView->slotCode);
        self::assertSame('Water', $slotView->productName);
        self::assertSame(65, $slotView->priceCents);
    }
}
