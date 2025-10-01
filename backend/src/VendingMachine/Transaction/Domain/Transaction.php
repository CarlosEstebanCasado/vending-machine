<?php

declare(strict_types=1);

namespace App\VendingMachine\Transaction\Domain;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionId;
use App\VendingMachine\Transaction\Domain\ValueObject\AdminUserId;
use App\VendingMachine\Transaction\Domain\ValueObject\TransactionId;
use App\VendingMachine\Transaction\Domain\ValueObject\TransactionStatus;
use App\VendingMachine\Transaction\Domain\ValueObject\TransactionType;
use DateTimeImmutable;
use DomainException;

final class Transaction
{
    /**
     * @param TransactionItem[] $items
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private readonly TransactionId $id,
        private TransactionType $type,
        private TransactionStatus $status,
        private array $items,
        private Money $totalPaid,
        private CoinBundle $changeDispensed,
        private readonly ?VendingSessionId $sessionId,
        private readonly ?AdminUserId $adminUserId,
        private array $metadata,
        private DateTimeImmutable $createdAt,
        private ?string $failureReason,
    ) {
        $this->assertItems($items);
        $this->assertMetadata($metadata);
        $this->ensureMonetaryValues();
        $this->ensureConsistency();
    }

    /**
     * @param TransactionItem[] $items
     * @param array<string, mixed> $metadata
     */
    public static function record(
        TransactionId $id,
        TransactionType $type,
        array $items,
        Money $totalPaid,
        CoinBundle $changeDispensed,
        ?VendingSessionId $sessionId = null,
        ?AdminUserId $adminUserId = null,
        array $metadata = [],
        ?DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: $id,
            type: $type,
            status: TransactionStatus::Completed,
            items: $items,
            totalPaid: $totalPaid,
            changeDispensed: $changeDispensed,
            sessionId: $sessionId,
            adminUserId: $adminUserId,
            metadata: $metadata,
            createdAt: $createdAt ?? new DateTimeImmutable(),
            failureReason: null,
        );
    }

    /**
     * @param TransactionItem[] $items
     * @param array<string, mixed> $metadata
     */
    public static function restore(
        TransactionId $id,
        TransactionType $type,
        TransactionStatus $status,
        array $items,
        Money $totalPaid,
        CoinBundle $changeDispensed,
        ?VendingSessionId $sessionId,
        ?AdminUserId $adminUserId,
        array $metadata,
        DateTimeImmutable $createdAt,
        ?string $failureReason,
    ): self {
        return new self(
            $id,
            $type,
            $status,
            $items,
            $totalPaid,
            $changeDispensed,
            $sessionId,
            $adminUserId,
            $metadata,
            $createdAt,
            $failureReason,
        );
    }

    public function id(): TransactionId
    {
        return $this->id;
    }

    public function type(): TransactionType
    {
        return $this->type;
    }

    /**
     * @return TransactionItem[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function totalPaid(): Money
    {
        return $this->totalPaid;
    }

    public function changeDispensed(): CoinBundle
    {
        return $this->changeDispensed;
    }

    public function sessionId(): ?VendingSessionId
    {
        return $this->sessionId;
    }

    public function adminUserId(): ?AdminUserId
    {
        return $this->adminUserId;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function status(): TransactionStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function markFailed(?string $reason = null): void
    {
        if ($this->status->isFailed()) {
            return;
        }

        if (null !== $reason && '' === trim($reason)) {
            throw new DomainException('Failure reason cannot be empty string.');
        }

        $this->status = TransactionStatus::Failed;
        $this->failureReason = $reason;
        $this->ensureConsistency();
    }

    public function markCompleted(): void
    {
        if ($this->status->isCompleted()) {
            return;
        }

        $this->status = TransactionStatus::Completed;
        $this->failureReason = null;
        $this->ensureConsistency();
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $trimmed = trim($key);
        if ('' === $trimmed) {
            throw new DomainException('Metadata key cannot be empty.');
        }

        $this->metadata[$trimmed] = $value;
    }

    private function assertItems(array $items): void
    {
        foreach ($items as $item) {
            if (!$item instanceof TransactionItem) {
                throw new \InvalidArgumentException('All items must be instances of TransactionItem.');
            }
        }
    }

    private function assertMetadata(array $metadata): void
    {
        foreach ($metadata as $key => $_value) {
            if (!is_string($key) || '' === trim($key)) {
                throw new \InvalidArgumentException('Metadata keys must be non-empty strings.');
            }
        }
    }

    private function ensureMonetaryValues(): void
    {
        if ($this->totalPaid->amountInCents() < 0) {
            throw new DomainException('Total paid cannot be negative.');
        }
    }

    private function ensureConsistency(): void
    {
        if ($this->status->isCompleted() && null !== $this->failureReason) {
            throw new DomainException('Completed transactions cannot retain a failure reason.');
        }

        if ($this->status->isFailed() && null !== $this->failureReason && '' === trim($this->failureReason)) {
            throw new DomainException('Failure reason cannot be blank.');
        }

        $itemsSubtotal = $this->calculateItemsSubtotal();

        $changeAmount = $this->changeDispensed->totalAmount();

        $this->ensureTypeSpecificRules($itemsSubtotal, $changeAmount);
    }

    private function calculateItemsSubtotal(): Money
    {
        $subtotal = 0;
        foreach ($this->items as $item) {
            $subtotal += $item->subtotal()->amountInCents();
        }

        return Money::fromCents($subtotal);
    }

    private function ensureTypeSpecificRules(Money $itemsSubtotal, Money $changeAmount): void
    {
        switch ($this->type) {
            case TransactionType::Vend:
                $this->assertVendTransaction($itemsSubtotal, $changeAmount);
                break;
            case TransactionType::Return:
                $this->assertReturnTransaction($changeAmount);
                break;
            case TransactionType::Restock:
                $this->assertRestockTransaction($itemsSubtotal, $changeAmount);
                break;
            case TransactionType::Adjustment:
                $this->assertAdjustmentTransaction($changeAmount);
                break;
        }
    }

    private function assertVendTransaction(Money $itemsSubtotal, Money $changeAmount): void
    {
        if (empty($this->items)) {
            throw new DomainException('Vend transactions require at least one item.');
        }

        if (null === $this->sessionId) {
            throw new DomainException('Vend transactions must be linked to a session.');
        }

        if ($this->totalPaid->compareTo($itemsSubtotal) < 0) {
            throw new DomainException('Total paid cannot be less than items subtotal.');
        }

        $expectedChange = $this->totalPaid->subtract($itemsSubtotal);

        if (!$expectedChange->equals($changeAmount)) {
            throw new DomainException('Change dispensed must match total paid minus items subtotal.');
        }
    }

    private function assertReturnTransaction(Money $changeAmount): void
    {
        if (null === $this->sessionId) {
            throw new DomainException('Return transactions must be linked to a session.');
        }

        if (!empty($this->items)) {
            foreach ($this->items as $item) {
                if ($item->quantity() <= 0) {
                    throw new DomainException('Return transactions cannot contain invalid item quantities.');
                }
            }
        }

        if ($this->totalPaid->amountInCents() !== 0) {
            throw new DomainException('Return transactions should not record customer payments.');
        }

        if ($changeAmount->amountInCents() <= 0) {
            throw new DomainException('Return transactions must dispense change.');
        }
    }

    private function assertRestockTransaction(Money $itemsSubtotal, Money $changeAmount): void
    {
        if (null === $this->adminUserId) {
            throw new DomainException('Restock transactions require an admin user.');
        }

        if (empty($this->items)) {
            throw new DomainException('Restock transactions require items.');
        }

        if ($changeAmount->amountInCents() !== 0) {
            throw new DomainException('Restock transactions cannot dispense change.');
        }

        if ($itemsSubtotal->amountInCents() <= 0) {
            throw new DomainException('Restock items subtotal must be positive.');
        }
    }

    private function assertAdjustmentTransaction(Money $changeAmount): void
    {
        if (null === $this->adminUserId) {
            throw new DomainException('Adjustment transactions require an admin user.');
        }

        if ($changeAmount->amountInCents() !== 0) {
            throw new DomainException('Adjustment transactions cannot dispense change.');
        }
    }
}
