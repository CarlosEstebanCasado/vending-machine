<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Infrastructure\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'machine_slots')]
class SlotProjectionDocument
{
    #[ODM\Id(strategy: 'NONE', type: 'string')]
    private string $id;

    #[ODM\Field(type: 'string')]
    private string $machineId;

    #[ODM\Field(type: 'string')]
    private string $slotCode;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $productId = null;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $productName = null;

    #[ODM\Field(type: 'int', nullable: true)]
    private ?int $priceCents = null;

    #[ODM\Field(type: 'int')]
    private int $quantity;

    #[ODM\Field(type: 'int')]
    private int $capacity;

    #[ODM\Field(type: 'int')]
    private int $recommendedSlotQuantity;

    #[ODM\Field(type: 'string')]
    private string $status;

    #[ODM\Field(type: 'bool')]
    private bool $lowStock;

    public function __construct(
        string $machineId,
        string $slotCode,
        int $capacity,
        int $recommendedSlotQuantity,
        int $quantity,
        string $status,
        bool $lowStock,
        ?string $productId = null,
        ?string $productName = null,
        ?int $priceCents = null,
    ) {
        $this->id = sprintf('%s-%s', $machineId, $slotCode);
        $this->machineId = $machineId;
        $this->slotCode = $slotCode;
        $this->capacity = $capacity;
        $this->recommendedSlotQuantity = $recommendedSlotQuantity;
        $this->quantity = $quantity;
        $this->status = $status;
        $this->lowStock = $lowStock;
        $this->productId = $productId;
        $this->productName = $productName;
        $this->priceCents = $priceCents;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function machineId(): string
    {
        return $this->machineId;
    }

    public function slotCode(): string
    {
        return $this->slotCode;
    }

    public function productId(): ?string
    {
        return $this->productId;
    }

    public function productName(): ?string
    {
        return $this->productName;
    }

    public function priceCents(): ?int
    {
        return $this->priceCents;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function recommendedSlotQuantity(): int
    {
        return $this->recommendedSlotQuantity;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function lowStock(): bool
    {
        return $this->lowStock;
    }
}
