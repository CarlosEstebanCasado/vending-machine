<?php

declare(strict_types=1);

namespace App\VendingMachine\Transaction\Domain;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use InvalidArgumentException;

final class TransactionItem
{
    private function __construct(
        private readonly ProductId $productId,
        private readonly int $quantity,
        private readonly Money $unitPrice,
    ) {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Transaction item quantity must be greater than zero.');
        }
    }

    public static function create(ProductId $productId, int $quantity, Money $unitPrice): self
    {
        return new self($productId, $quantity, $unitPrice);
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function subtotal(): Money
    {
        return Money::fromCents($this->unitPrice->amountInCents() * $this->quantity);
    }
}
