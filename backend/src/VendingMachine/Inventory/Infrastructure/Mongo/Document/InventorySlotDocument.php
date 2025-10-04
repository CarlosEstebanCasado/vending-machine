<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Infrastructure\Mongo\Document;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'inventory_slots')]
class InventorySlotDocument
{
    #[ODM\Id(strategy: 'NONE', type: 'string')]
    private string $id;

    #[ODM\Field(type: 'string')]
    private string $machineId;

    #[ODM\Field(type: 'string')]
    private string $code;

    #[ODM\Field(type: 'int')]
    private int $capacity;

    #[ODM\Field(type: 'int')]
    private int $quantity;

    #[ODM\Field(type: 'int')]
    private int $restockThreshold;

    #[ODM\Field(type: 'string')]
    private string $status;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $productId;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $machineId,
        string $code,
        int $capacity,
        int $quantity,
        int $restockThreshold,
        string $status,
        ?string $productId,
        ?string $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->machineId = $machineId;
        $this->code = $code;
        $this->capacity = $capacity;
        $this->quantity = $quantity;
        $this->restockThreshold = $restockThreshold;
        $this->status = $status;
        $this->productId = $productId;
        $this->id = $id ?? sprintf('%s-%s', $machineId, $code);
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function machineId(): string
    {
        return $this->machineId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function restockThreshold(): int
    {
        return $this->restockThreshold;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function productId(): ?string
    {
        return $this->productId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        int $capacity,
        int $quantity,
        int $restockThreshold,
        string $status,
        ?string $productId,
    ): void {
        $this->capacity = $capacity;
        $this->quantity = $quantity;
        $this->restockThreshold = $restockThreshold;
        $this->status = $status;
        $this->productId = $productId;
        $this->updatedAt = new DateTimeImmutable();
    }
}
