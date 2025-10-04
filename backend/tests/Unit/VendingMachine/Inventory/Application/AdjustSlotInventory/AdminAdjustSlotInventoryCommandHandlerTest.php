<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Application\AdjustSlotInventory;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\VendingMachine\Inventory\Domain\InventorySlotMother;
use App\Tests\Unit\VendingMachine\Product\Domain\ProductMother;
use App\VendingMachine\Inventory\Application\AdjustSlotInventory\AdjustSlotInventoryOperation;
use App\VendingMachine\Inventory\Application\AdjustSlotInventory\AdminAdjustSlotInventoryCommand;
use App\VendingMachine\Inventory\Application\AdjustSlotInventory\AdminAdjustSlotInventoryCommandHandler;
use App\VendingMachine\Inventory\Domain\InventorySlot;
use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Product\Domain\ProductRepository;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Product\Domain\ValueObject\ProductName;
use App\VendingMachine\Product\Domain\ValueObject\ProductSku;
use App\VendingMachine\Product\Domain\ValueObject\ProductStatus;
use App\VendingMachine\Product\Domain\ValueObject\RecommendedSlotQuantity;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AdminAdjustSlotInventoryCommandHandlerTest extends TestCase
{
    public function testRestockAssignsProductAndUpdatesProjection(): void
    {
        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(0),
            restockThreshold: RestockThreshold::fromInt(2),
            status: SlotStatus::Available,
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', self::callback(static fn (SlotCode $code): bool => '11' === $code->value()))
            ->willReturn($slot);
        $slotRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (InventorySlot $savedSlot): bool {
                return 3 === $savedSlot->quantity()->value()
                    && 'product-1' === $savedSlot->productId()?->value()
                    && SlotStatus::Available === $savedSlot->status();
            }), 'machine-1');

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
            ->method('find')
            ->with(ProductId::fromString('product-1'))
            ->willReturn($product);

        $projection = new SlotProjectionDocument(
            machineId: 'machine-1',
            slotCode: '11',
            capacity: 10,
            recommendedSlotQuantity: 8,
            quantity: 0,
            status: SlotStatus::Available->value,
            lowStock: true,
            productId: null,
            productName: null,
            priceCents: null,
        );

        $documentRepository = $this->createMock(DocumentRepository::class);
        $documentRepository->expects(self::once())
            ->method('findOneBy')
            ->with([
                'machineId' => 'machine-1',
                'slotCode' => '11',
            ])
            ->willReturn($projection);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(SlotProjectionDocument::class)
            ->willReturn($documentRepository);
        $documentManager->expects(self::once())
            ->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $command = new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Restock,
            quantity: 3,
            productId: 'product-1',
        );

        $handler->handle($command);

        self::assertSame(3, $projection->quantity());
        self::assertSame('product-1', $projection->productId());
        self::assertSame('Water', $projection->productName());
        self::assertSame(65, $projection->priceCents());
    }

    public function testQuantityMustBeGreaterThanZero(): void
    {
        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::never())->method('findByMachineAndCode');
        $slotRepository->expects(self::never())->method('save');

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::never())->method('find');
        $productRepository->expects(self::never())->method('all');

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::never())->method('getRepository');
        $documentManager->expects(self::never())->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero.');

        $handler->handle(new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Restock,
            quantity: 0,
            productId: 'product-1',
        ));
    }

    public function testRestockFailsWhenSlotDoesNotExist(): void
    {
        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->willReturn(null);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::never())->method('find');
        $productRepository->expects(self::never())->method('all');

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::never())->method('getRepository');
        $documentManager->expects(self::never())->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot "11" not found for machine "machine-1".');

        $handler->handle(new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Restock,
            quantity: 3,
            productId: 'product-1',
        ));
    }

    public function testRestockRequiresProductId(): void
    {
        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(0),
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->willReturn($slot);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::never())->method('find');
        $productRepository->expects(self::never())->method('all');

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::never())->method('getRepository');
        $documentManager->expects(self::never())->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product id must be provided when restocking a slot.');

        $handler->handle(new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Restock,
            quantity: 3,
            productId: null,
        ));
    }

    public function testRestockFailsWhenProductNotFound(): void
    {
        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(0),
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->willReturn($slot);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::once())
            ->method('find')
            ->with(ProductId::fromString('product-unknown'))
            ->willReturn(null);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::never())->method('getRepository');
        $documentManager->expects(self::never())->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product not found.');

        $handler->handle(new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Restock,
            quantity: 3,
            productId: 'product-unknown',
        ));
    }

    public function testRestockFailsWhenSlotAssignedToAnotherProduct(): void
    {
        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(2),
            restockThreshold: RestockThreshold::fromInt(1),
            status: SlotStatus::Available,
            productId: ProductId::fromString('product-1'),
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->willReturn($slot);

        $product = ProductMother::random(
            id: ProductId::fromString('product-2'),
            sku: ProductSku::fromString('SKU-002'),
            name: ProductName::fromString('Juice'),
            price: Money::fromCents(120),
            status: ProductStatus::Active,
            recommendedSlotQuantity: RecommendedSlotQuantity::fromInt(6),
        );

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::once())
            ->method('find')
            ->with(ProductId::fromString('product-2'))
            ->willReturn($product);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::never())->method('getRepository');
        $documentManager->expects(self::never())->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot already assigned to a different product.');

        $handler->handle(new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Restock,
            quantity: 1,
            productId: 'product-2',
        ));
    }

    public function testRestockFailsWhenSlotReserved(): void
    {
        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(3),
            restockThreshold: RestockThreshold::fromInt(1),
            status: SlotStatus::Reserved,
            productId: ProductId::fromString('product-1'),
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', self::callback(static fn (SlotCode $code): bool => '11' === $code->value()))
            ->willReturn($slot);
        $slotRepository->expects(self::never())
            ->method('save');

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::never())->method('find');

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::never())->method('getRepository');
        $documentManager->expects(self::never())->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot cannot be restocked while it is reserved by an active session.');

        $handler->handle(new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Restock,
            quantity: 2,
            productId: 'product-1',
        ));
    }

    public function testWithdrawFailsWhenSlotReserved(): void
    {
        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(3),
            restockThreshold: RestockThreshold::fromInt(1),
            status: SlotStatus::Reserved,
            productId: ProductId::fromString('product-1'),
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', self::callback(static fn (SlotCode $code): bool => '11' === $code->value()))
            ->willReturn($slot);
        $slotRepository->expects(self::never())
            ->method('save');

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::never())->method('find');

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::never())->method('getRepository');
        $documentManager->expects(self::never())->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot cannot be adjusted while it is reserved by an active session.');

        $handler->handle(new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Withdraw,
            quantity: 1,
            productId: 'product-1',
        ));
    }

    public function testWithdrawClearsSlotWhenEmpty(): void
    {
        $slot = InventorySlotMother::random(
            id: InventorySlotId::fromString('slot-1'),
            code: SlotCode::fromString('11'),
            capacity: SlotCapacity::fromInt(10),
            quantity: SlotQuantity::fromInt(2),
            restockThreshold: RestockThreshold::fromInt(1),
            status: SlotStatus::Available,
            productId: ProductId::fromString('product-1'),
        );

        $slotRepository = $this->createMock(InventorySlotRepository::class);
        $slotRepository->expects(self::once())
            ->method('findByMachineAndCode')
            ->with('machine-1', self::callback(static fn (SlotCode $code): bool => '11' === $code->value()))
            ->willReturn($slot);
        $slotRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (InventorySlot $savedSlot): bool {
                return 0 === $savedSlot->quantity()->value()
                    && null === $savedSlot->productId()
                    && SlotStatus::Disabled === $savedSlot->status();
            }), 'machine-1');

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects(self::never())
            ->method('find');

        $projection = new SlotProjectionDocument(
            machineId: 'machine-1',
            slotCode: '11',
            capacity: 10,
            recommendedSlotQuantity: 8,
            quantity: 2,
            status: SlotStatus::Available->value,
            lowStock: false,
            productId: 'product-1',
            productName: 'Water',
            priceCents: 65,
        );

        $documentRepository = $this->createMock(DocumentRepository::class);
        $documentRepository->expects(self::once())
            ->method('findOneBy')
            ->willReturn($projection);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->willReturn($documentRepository);
        $documentManager->expects(self::once())
            ->method('flush');

        $handler = new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $documentManager);

        $command = new AdminAdjustSlotInventoryCommand(
            machineId: 'machine-1',
            slotCode: '11',
            operation: AdjustSlotInventoryOperation::Withdraw,
            quantity: 2,
            productId: null,
        );

        $handler->handle($command);

        self::assertSame(0, $projection->quantity());
        self::assertNull($projection->productId());
        self::assertSame(SlotStatus::Disabled->value, $projection->status());
    }
}
