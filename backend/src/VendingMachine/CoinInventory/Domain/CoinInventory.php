<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Domain;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use DomainException;

final class CoinInventory
{
    private function __construct(
        private CoinBundle $available,
        private CoinBundle $reserved,
    ) {
    }

    public static function create(?CoinBundle $available = null): self
    {
        return new self(
            $available ?? CoinBundle::empty(),
            CoinBundle::empty()
        );
    }

    public static function restore(CoinBundle $available, CoinBundle $reserved): self
    {
        if (!$available->includesAtLeast($reserved)) {
            throw new DomainException('Reserved coins cannot exceed available coins.');
        }

        return new self($available, $reserved);
    }

    public function availableCoins(): CoinBundle
    {
        return $this->available;
    }

    public function reservedCoins(): CoinBundle
    {
        return $this->reserved;
    }

    public function deposit(CoinBundle $bundle): void
    {
        $this->available = $this->available->add($bundle);
    }

    public function withdraw(CoinBundle $bundle): void
    {
        if (!$this->available->includesAtLeast($bundle)) {
            throw new DomainException('Not enough coins available to withdraw the requested bundle.');
        }

        $this->available = $this->available->subtract($bundle);
    }

    public function reserveChange(CoinBundle $bundle): void
    {
        $bundle->assertContainsOnlyChangeCoins();

        if (!$this->available->includesAtLeast($bundle)) {
            throw new DomainException('Not enough coins available to reserve the requested change.');
        }

        $this->available = $this->available->subtract($bundle);
        $this->reserved = $this->reserved->add($bundle);
    }

    public function releaseReserved(CoinBundle $bundle): void
    {
        $bundle->assertContainsOnlyChangeCoins();

        if (!$this->reserved->includesAtLeast($bundle)) {
            throw new DomainException('Cannot release more coins than currently reserved.');
        }

        $this->reserved = $this->reserved->subtract($bundle);
        $this->available = $this->available->add($bundle);
    }

    public function commitReserved(CoinBundle $bundle): void
    {
        $bundle->assertContainsOnlyChangeCoins();

        if (!$this->reserved->includesAtLeast($bundle)) {
            throw new DomainException('Cannot commit more coins than currently reserved.');
        }

        $this->reserved = $this->reserved->subtract($bundle);
    }

    public function planChangeFor(Money $amount): CoinBundle
    {
        $remaining = $amount->amountInCents();

        if ($remaining < 0) {
            throw new DomainException('Change amount cannot be negative.');
        }

        if (0 === $remaining) {
            return CoinBundle::empty();
        }

        $plan = [];
        $available = $this->available;

        foreach (CoinDenomination::changeDenominations() as $denomination) {
            $coinValue = $denomination->value;
            $availableQuantity = $available->quantityFor($denomination)->value();
            $maxNeeded = intdiv($remaining, $coinValue);
            $use = min($maxNeeded, $availableQuantity);

            if ($use > 0) {
                $plan[$coinValue] = $use;
                $remaining -= $use * $coinValue;
            }
        }

        if (0 !== $remaining) {
            throw new DomainException('Exact change cannot be provided with the available coins.');
        }

        return CoinBundle::fromArray($plan);
    }

    public function totalAvailableAmount(): Money
    {
        return $this->available->totalAmount();
    }

    public function totalReservedAmount(): Money
    {
        return $this->reserved->totalAmount();
    }
}
