<?php

declare(strict_types=1);

namespace App\VendingMachine\Product\Domain;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Product\Domain\ValueObject\ProductName;
use App\VendingMachine\Product\Domain\ValueObject\ProductSku;
use App\VendingMachine\Product\Domain\ValueObject\ProductStatus;
use App\VendingMachine\Product\Domain\ValueObject\RecommendedSlotQuantity;

final class Product
{
    private function __construct(
        private readonly ProductId $id,
        private ProductSku $sku,
        private ProductName $name,
        private Money $price,
        private ProductStatus $status,
        private RecommendedSlotQuantity $recommendedSlotQuantity,
    ) {
    }

    public static function create(
        ProductId $id,
        ProductSku $sku,
        ProductName $name,
        Money $price,
        ?ProductStatus $status = null,
        ?RecommendedSlotQuantity $recommendedSlotQuantity = null,
    ): self {
        return new self(
            id: $id,
            sku: $sku,
            name: $name,
            price: $price,
            status: $status ?? ProductStatus::Active,
            recommendedSlotQuantity: $recommendedSlotQuantity ?? RecommendedSlotQuantity::fromInt(0)
        );
    }

    public static function restore(
        ProductId $id,
        ProductSku $sku,
        ProductName $name,
        Money $price,
        ProductStatus $status,
        RecommendedSlotQuantity $recommendedSlotQuantity,
    ): self {
        return new self(id: $id, sku: $sku, name: $name, price: $price, status: $status, recommendedSlotQuantity: $recommendedSlotQuantity);
    }

    public function id(): ProductId
    {
        return $this->id;
    }

    public function sku(): ProductSku
    {
        return $this->sku;
    }

    public function name(): ProductName
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function status(): ProductStatus
    {
        return $this->status;
    }

    public function recommendedSlotQuantity(): RecommendedSlotQuantity
    {
        return $this->recommendedSlotQuantity;
    }

    public function rename(ProductName $name): void
    {
        $this->name = $name;
    }

    public function reprice(Money $price): void
    {
        $this->price = $price;
    }

    public function changeSku(ProductSku $sku): void
    {
        $this->sku = $sku;
    }

    public function activate(): void
    {
        $this->status = ProductStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = ProductStatus::Inactive;
    }

    public function updateRecommendedSlotQuantity(RecommendedSlotQuantity $quantity): void
    {
        $this->recommendedSlotQuantity = $quantity;
    }
}
