<?php

declare(strict_types=1);

namespace App\VendingMachine\Product\Infrastructure\Mongo;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\Product\Domain\Product;
use App\VendingMachine\Product\Domain\ProductRepository;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Product\Domain\ValueObject\ProductName;
use App\VendingMachine\Product\Domain\ValueObject\ProductSku;
use App\VendingMachine\Product\Domain\ValueObject\ProductStatus;
use App\VendingMachine\Product\Domain\ValueObject\RecommendedSlotQuantity;
use App\VendingMachine\Product\Infrastructure\Mongo\Document\ProductDocument;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

final class MongoProductRepository implements ProductRepository
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function find(ProductId $id): ?Product
    {
        /** @var ProductDocument|null $document */
        $document = $this->documentManager->find(ProductDocument::class, $id->value());

        if (null === $document) {
            return null;
        }

        return $this->toDomain($document);
    }

    public function findBySku(ProductSku $sku): ?Product
    {
        /** @var ProductDocument|null $document */
        $document = $this->repository()->findOneBy(['sku' => $sku->value()]);

        if (null === $document) {
            return null;
        }

        return $this->toDomain($document);
    }

    public function all(): array
    {
        /** @var ProductDocument[] $documents */
        $documents = $this->repository()->findAll();

        return array_map(fn (ProductDocument $document): Product => $this->toDomain($document), $documents);
    }

    public function save(Product $product): void
    {
        /** @var ProductDocument|null $document */
        $document = $this->documentManager->find(ProductDocument::class, $product->id()->value());

        if (null === $document) {
            $document = new ProductDocument(
                id: $product->id()->value(),
                sku: $product->sku()->value(),
                name: $product->name()->value(),
                priceCents: $product->price()->amountInCents(),
                status: $product->status()->value,
                recommendedSlotQuantity: $product->recommendedSlotQuantity()->value(),
            );
            $this->documentManager->persist($document);
        } else {
            $document->update(
                $product->sku()->value(),
                $product->name()->value(),
                $product->price()->amountInCents(),
                $product->status()->value,
                $product->recommendedSlotQuantity()->value(),
            );
        }

        $this->documentManager->flush();
    }

    private function toDomain(ProductDocument $document): Product
    {
        return Product::restore(
            ProductId::fromString($document->id()),
            ProductSku::fromString($document->sku()),
            ProductName::fromString($document->name()),
            Money::fromCents($document->priceCents()),
            ProductStatus::from($document->status()),
            RecommendedSlotQuantity::fromInt($document->recommendedSlotQuantity()),
        );
    }

    private function repository(): DocumentRepository
    {
        /** @var DocumentRepository $repository */
        $repository = $this->documentManager->getRepository(ProductDocument::class);

        return $repository;
    }
}
