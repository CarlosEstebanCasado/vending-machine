<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Inventory\Infrastructure\Mongo;

use App\VendingMachine\Inventory\Domain\InventorySlot;
use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Inventory\Infrastructure\Mongo\Document\InventorySlotDocument;
use App\VendingMachine\Inventory\Infrastructure\Mongo\MongoInventorySlotRepository;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use PHPUnit\Framework\TestCase;

final class MongoInventorySlotRepositoryTest extends TestCase
{
    public function testFindReturnsInventorySlot(): void
    {
        $document = new InventorySlotDocument(
            machineId: 'machine-1',
            code: '11',
            capacity: 10,
            quantity: 4,
            restockThreshold: 2,
            status: SlotStatus::Available->value,
            productId: 'product-1',
            id: 'slot-1',
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(InventorySlotDocument::class, 'slot-1')
            ->willReturn($document);

        $repository = new MongoInventorySlotRepository($documentManager);

        $slot = $repository->find(InventorySlotId::fromString('slot-1'));

        self::assertNotNull($slot);
        self::assertSame('slot-1', $slot->id()->value());
        self::assertSame('11', $slot->code()->value());
        self::assertSame(10, $slot->capacity()->value());
        self::assertSame(4, $slot->quantity()->value());
    }

    public function testSavePersistsNewSlot(): void
    {
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->willReturn(null);

        $documentManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (InventorySlotDocument $doc): bool {
                return 'machine-1' === $doc->machineId()
                    && '11' === $doc->code()
                    && 5 === $doc->quantity()
                    && 2 === $doc->restockThreshold();
            }));

        $documentManager->expects(self::once())
            ->method('flush');

        $repository = new MongoInventorySlotRepository($documentManager);

        $slot = InventorySlot::restore(
            InventorySlotId::fromString('slot-1'),
            SlotCode::fromString('11'),
            SlotCapacity::fromInt(10),
            SlotQuantity::fromInt(5),
            RestockThreshold::fromInt(2),
            SlotStatus::Available,
            ProductId::fromString('product-1'),
        );

        $repository->save($slot, 'machine-1');
    }

    public function testSaveUpdatesExistingSlot(): void
    {
        $document = new InventorySlotDocument(
            machineId: 'machine-1',
            code: '11',
            capacity: 10,
            quantity: 5,
            restockThreshold: 2,
            status: SlotStatus::Available->value,
            productId: 'product-1',
            id: 'slot-1',
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(InventorySlotDocument::class, 'slot-1')
            ->willReturn($document);

        $documentManager->expects(self::never())
            ->method('persist');

        $documentManager->expects(self::once())
            ->method('flush');

        $repository = new MongoInventorySlotRepository($documentManager);

        $slot = InventorySlot::restore(
            InventorySlotId::fromString('slot-1'),
            SlotCode::fromString('11'),
            SlotCapacity::fromInt(10),
            SlotQuantity::fromInt(3),
            RestockThreshold::fromInt(2),
            SlotStatus::Reserved,
            null,
        );

        $repository->save($slot, 'machine-1');

        self::assertSame(3, $document->quantity());
        self::assertSame('reserved', $document->status());
        self::assertNull($document->productId());
    }

    public function testFindByMachineAndCodeReturnsInventorySlot(): void
    {
        $document = new InventorySlotDocument(
            machineId: 'machine-1',
            code: '11',
            capacity: 10,
            quantity: 5,
            restockThreshold: 2,
            status: SlotStatus::Available->value,
            productId: 'product-1',
            id: 'slot-1',
        );

        $documentRepository = $this->createMock(DocumentRepository::class);
        $documentRepository->expects(self::once())
            ->method('findOneBy')
            ->with([
                'machineId' => 'machine-1',
                'code' => '11',
            ])
            ->willReturn($document);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(InventorySlotDocument::class)
            ->willReturn($documentRepository);

        $repository = new MongoInventorySlotRepository($documentManager);

        $slot = $repository->findByMachineAndCode('machine-1', SlotCode::fromString('11'));

        self::assertNotNull($slot);
        self::assertSame('slot-1', $slot->id()->value());
        self::assertSame('product-1', $slot->productId()?->value());
    }

    public function testFindByMachineReturnsSlots(): void
    {
        $documents = [
            new InventorySlotDocument(
                machineId: 'machine-1',
                code: '11',
                capacity: 10,
                quantity: 5,
                restockThreshold: 2,
                status: SlotStatus::Available->value,
                productId: 'product-1',
                id: 'slot-1',
            ),
            new InventorySlotDocument(
                machineId: 'machine-1',
                code: '12',
                capacity: 8,
                quantity: 1,
                restockThreshold: 1,
                status: SlotStatus::Reserved->value,
                productId: null,
                id: 'slot-2',
            ),
        ];

        $documentRepository = $this->createMock(DocumentRepository::class);
        $documentRepository->expects(self::once())
            ->method('findBy')
            ->with(['machineId' => 'machine-1'])
            ->willReturn($documents);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(InventorySlotDocument::class)
            ->willReturn($documentRepository);

        $repository = new MongoInventorySlotRepository($documentManager);

        $slots = $repository->findByMachine('machine-1');

        self::assertCount(2, $slots);
        self::assertSame(['slot-1', 'slot-2'], array_map(static fn (InventorySlot $slot): string => $slot->id()->value(), $slots));
    }
}
