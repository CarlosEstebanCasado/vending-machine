<?php

declare(strict_types=1);

namespace App\VendingMachine\Product\Infrastructure\Mongo\Document;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'products')]
class ProductDocument
{
    #[ODM\Id(strategy: 'NONE', type: 'string')]
    private string $id;

    #[ODM\Field(type: 'string')]
    private string $sku;

    #[ODM\Field(type: 'string')]
    private string $name;

    #[ODM\Field(type: 'int')]
    private int $priceCents;

    #[ODM\Field(type: 'string')]
    private string $status;

    #[ODM\Field(type: 'int')]
    private int $recommendedSlotQuantity;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $createdAt;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $sku,
        string $name,
        int $priceCents,
        string $status,
        int $recommendedSlotQuantity,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->name = $name;
        $this->priceCents = $priceCents;
        $this->status = $status;
        $this->recommendedSlotQuantity = $recommendedSlotQuantity;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sku(): string
    {
        return $this->sku;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function priceCents(): int
    {
        return $this->priceCents;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function recommendedSlotQuantity(): int
    {
        return $this->recommendedSlotQuantity;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(string $sku, string $name, int $priceCents, string $status, int $recommendedSlotQuantity): void
    {
        $this->sku = $sku;
        $this->name = $name;
        $this->priceCents = $priceCents;
        $this->status = $status;
        $this->recommendedSlotQuantity = $recommendedSlotQuantity;
        $this->updatedAt = new DateTimeImmutable();
    }
}
