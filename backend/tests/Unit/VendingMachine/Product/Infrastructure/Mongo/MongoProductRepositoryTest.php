<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Product\Infrastructure\Mongo;

use App\Shared\Money\Domain\Money;
use App\Tests\Unit\VendingMachine\Product\Domain\ProductMother;
use App\VendingMachine\Product\Domain\Product;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Product\Domain\ValueObject\ProductName;
use App\VendingMachine\Product\Domain\ValueObject\ProductSku;
use App\VendingMachine\Product\Domain\ValueObject\ProductStatus;
use App\VendingMachine\Product\Domain\ValueObject\RecommendedSlotQuantity;
use App\VendingMachine\Product\Infrastructure\Mongo\Document\ProductDocument;
use App\VendingMachine\Product\Infrastructure\Mongo\MongoProductRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use PHPUnit\Framework\TestCase;

final class MongoProductRepositoryTest extends TestCase
{
    public function testFindReturnsDomainProduct(): void
    {
        $document = new ProductDocument(
            'product-1',
            'SKU-001',
            'Water',
            65,
            'active',
            8,
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ProductDocument::class, 'product-1')
            ->willReturn($document);

        $repository = new MongoProductRepository($documentManager);
        $product = $repository->find(ProductId::fromString('product-1'));

        self::assertNotNull($product);
        self::assertSame('product-1', $product->id()->value());
        self::assertSame('SKU-001', $product->sku()->value());
        self::assertSame('Water', $product->name()->value());
        self::assertSame(65, $product->price()->amountInCents());
    }

    public function testSavePersistsNewProduct(): void
    {
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->willReturn(null);

        $documentManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (ProductDocument $document): bool {
                return 'product-1' === $document->id()
                    && 'SKU-001' === $document->sku()
                    && 'Water' === $document->name()
                    && 65 === $document->priceCents();
            }));

        $documentManager->expects(self::once())
            ->method('flush');

        $repository = new MongoProductRepository($documentManager);
        $product = ProductMother::random(
            id: ProductId::fromString('product-1'),
            sku: ProductSku::fromString('SKU-001'),
            name: ProductName::fromString('Water'),
            price: Money::fromCents(65),
            status: ProductStatus::Active,
            recommendedSlotQuantity: RecommendedSlotQuantity::fromInt(8),
        );

        $repository->save($product);
    }

    public function testSaveUpdatesExistingProduct(): void
    {
        $existing = new ProductDocument(
            'product-1',
            'SKU-OLD',
            'Old Name',
            100,
            'inactive',
            4,
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ProductDocument::class, 'product-1')
            ->willReturn($existing);

        $documentManager->expects(self::never())
            ->method('persist');

        $documentManager->expects(self::once())
            ->method('flush');

        $repository = new MongoProductRepository($documentManager);
        $updated = ProductMother::random(
            id: ProductId::fromString('product-1'),
            sku: ProductSku::fromString('SKU-NEW'),
            name: ProductName::fromString('New Name'),
            price: Money::fromCents(150),
            status: ProductStatus::Active,
            recommendedSlotQuantity: RecommendedSlotQuantity::fromInt(8),
        );

        $repository->save($updated);

        self::assertSame('SKU-NEW', $existing->sku());
        self::assertSame('New Name', $existing->name());
        self::assertSame(150, $existing->priceCents());
        self::assertSame('active', $existing->status());
        self::assertSame(8, $existing->recommendedSlotQuantity());
    }

    public function testFindBySkuReturnsProduct(): void
    {
        $document = new ProductDocument(
            'product-1',
            'SKU-001',
            'Water',
            65,
            'active',
            8,
        );

        $documentRepository = $this->createMock(DocumentRepository::class);
        $documentRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['sku' => 'SKU-001'])
            ->willReturn($document);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(ProductDocument::class)
            ->willReturn($documentRepository);

        $repository = new MongoProductRepository($documentManager);

        $product = $repository->findBySku(ProductSku::fromString('SKU-001'));

        self::assertNotNull($product);
        self::assertSame('Water', $product->name()->value());
    }

    public function testAllReturnsProducts(): void
    {
        $documents = [
            new ProductDocument('product-1', 'SKU-001', 'Water', 65, 'active', 8),
            new ProductDocument('product-2', 'SKU-002', 'Soda', 150, 'inactive', 6),
        ];

        $documentRepository = $this->createMock(DocumentRepository::class);
        $documentRepository->expects(self::once())
            ->method('findAll')
            ->willReturn($documents);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(ProductDocument::class)
            ->willReturn($documentRepository);

        $repository = new MongoProductRepository($documentManager);

        $products = $repository->all();

        self::assertCount(2, $products);
        self::assertSame(['product-1', 'product-2'], array_map(static fn (Product $product): string => $product->id()->value(), $products));
    }
}
