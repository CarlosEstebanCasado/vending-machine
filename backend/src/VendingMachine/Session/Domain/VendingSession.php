<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Domain;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Session\Domain\ValueObject\SessionCloseReason;
use App\VendingMachine\Session\Domain\ValueObject\SessionClosure;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionId;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use DomainException;

final class VendingSession
{
    private function __construct(
        private readonly VendingSessionId $id,
        private VendingSessionState $state,
        private CoinBundle $insertedCoins,
        private Money $balance,
        private ?ProductId $selectedProductId,
        private ?CoinBundle $changePlan,
        private ?SessionCloseReason $closeReason,
    ) {
        $this->ensureStateConsistency();
    }

    public static function start(VendingSessionId $id): self
    {
        return new self(
            $id,
            VendingSessionState::Collecting,
            CoinBundle::empty(),
            Money::fromCents(0),
            null,
            null,
            null
        );
    }

    public static function restore(
        VendingSessionId $id,
        VendingSessionState $state,
        CoinBundle $insertedCoins,
        Money $balance,
        ?ProductId $selectedProductId,
        ?CoinBundle $changePlan,
        ?SessionCloseReason $closeReason,
    ): self {
        if (null !== $closeReason && !$state->isTerminal()) {
            throw new DomainException('Close reason is only allowed for closed sessions.');
        }

        if ($state->isTerminal() && null === $closeReason) {
            throw new DomainException('Closed sessions must include a close reason.');
        }

        return new self(
            $id,
            $state,
            $insertedCoins,
            $balance,
            $selectedProductId,
            $changePlan,
            $closeReason,
        );
    }

    public function id(): VendingSessionId
    {
        return $this->id;
    }

    public function state(): VendingSessionState
    {
        return $this->state;
    }

    public function insertedCoins(): CoinBundle
    {
        return $this->insertedCoins;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function selectedProductId(): ?ProductId
    {
        return $this->selectedProductId;
    }

    public function closeReason(): ?SessionCloseReason
    {
        return $this->closeReason;
    }

    public function isClosed(): bool
    {
        return $this->state->isTerminal();
    }

    public function insertCoin(CoinDenomination $denomination): void
    {
        $this->ensureStateIs(VendingSessionState::Collecting);

        $coinBundle = CoinBundle::fromArray([$denomination->value => 1]);
        $this->insertedCoins = $this->insertedCoins->add($coinBundle);
        $this->balance = $this->balance->add(Money::fromCents($denomination->value));
    }

    public function selectProduct(ProductId $productId): void
    {
        $this->ensureStateIs(VendingSessionState::Collecting);
        $this->selectedProductId = $productId;
    }

    public function clearSelection(): void
    {
        $this->ensureStateIs(VendingSessionState::Collecting);
        $this->selectedProductId = null;
    }

    public function approvePurchase(Money $price, CoinBundle $change): void
    {
        $this->ensureStateIs(VendingSessionState::Collecting);

        if (null === $this->selectedProductId) {
            throw new DomainException('Cannot approve purchase without a selected product.');
        }

        if ($this->balance->compareTo($price) < 0) {
            throw new DomainException('Insufficient balance for selected product.');
        }

        $expectedChange = $this->balance->subtract($price);
        $actualChange = $change->totalAmount();

        if (!$expectedChange->equals($actualChange)) {
            throw new DomainException('Provided change does not match expected amount.');
        }

        if (!$change->isEmpty()) {
            $change->assertContainsOnlyChangeCoins();
        }

        $this->changePlan = $change;
        $this->state = VendingSessionState::Ready;

        $this->ensureStateConsistency();
    }

    public function startDispensing(): CoinBundle
    {
        $this->ensureStateIs(VendingSessionState::Ready);
        if (null === $this->changePlan) {
            throw new DomainException('Change plan not defined.');
        }

        $this->state = VendingSessionState::Dispensing;
        $this->closeReason = SessionCloseReason::Completed;

        $change = $this->changePlan;
        $this->changePlan = null;
        $this->insertedCoins = CoinBundle::empty();
        $this->balance = Money::fromCents(0);
        $this->selectedProductId = null;

        $this->ensureStateConsistency();

        return $change;
    }

    public function cancel(): SessionClosure
    {
        $this->ensureNotTerminal();

        $this->state = VendingSessionState::Cancelled;
        $this->closeReason = SessionCloseReason::Cancelled;

        $closure = new SessionClosure($this->insertedCoins, $this->changePlan);

        $this->insertedCoins = CoinBundle::empty();
        $this->balance = Money::fromCents(0);
        $this->changePlan = null;
        $this->selectedProductId = null;

        $this->ensureStateConsistency();

        return $closure;
    }

    public function timeout(): SessionClosure
    {
        $this->ensureNotTerminal();

        $this->state = VendingSessionState::Timeout;
        $this->closeReason = SessionCloseReason::Timeout;

        $closure = new SessionClosure($this->insertedCoins, $this->changePlan);

        $this->insertedCoins = CoinBundle::empty();
        $this->balance = Money::fromCents(0);
        $this->changePlan = null;
        $this->selectedProductId = null;

        $this->ensureStateConsistency();

        return $closure;
    }

    private function ensureStateIs(VendingSessionState $expected): void
    {
        if ($this->state !== $expected) {
            throw new DomainException(sprintf('Session must be in %s state.', $expected->value));
        }
    }

    private function ensureNotTerminal(): void
    {
        if ($this->state->isTerminal()) {
            throw new DomainException('Session is already closed.');
        }
    }

    private function ensureStateConsistency(): void
    {
        if (VendingSessionState::Collecting === $this->state) {
            if (null !== $this->changePlan) {
                throw new DomainException('Collecting sessions cannot hold a change plan.');
            }

            if (null !== $this->closeReason) {
                throw new DomainException('Collecting sessions cannot have a close reason.');
            }
        }

        if (VendingSessionState::Ready === $this->state) {
            if (null === $this->selectedProductId) {
                throw new DomainException('Ready sessions require a selected product.');
            }

            if (null === $this->changePlan) {
                throw new DomainException('Ready sessions require a change plan.');
            }
        }

        if (VendingSessionState::Dispensing === $this->state) {
            if (SessionCloseReason::Completed !== $this->closeReason) {
                throw new DomainException('Dispensing sessions must be completed.');
            }

            if (!$this->insertedCoins->isEmpty()) {
                throw new DomainException('Dispensing sessions cannot retain inserted coins.');
            }

            if (null !== $this->changePlan) {
                throw new DomainException('Dispensing sessions cannot retain a change plan.');
            }

            if (0 !== $this->balance->amountInCents()) {
                throw new DomainException('Dispensing sessions must have zero balance.');
            }

            if (null !== $this->selectedProductId) {
                throw new DomainException('Dispensing sessions cannot retain a product selection.');
            }
        }

        if (VendingSessionState::Cancelled === $this->state) {
            if (SessionCloseReason::Cancelled !== $this->closeReason) {
                throw new DomainException('Cancelled sessions must include a cancelled close reason.');
            }

            if (!$this->insertedCoins->isEmpty()) {
                throw new DomainException('Cancelled sessions cannot retain inserted coins.');
            }

            if (null !== $this->changePlan) {
                throw new DomainException('Cancelled sessions cannot retain a change plan.');
            }

            if (0 !== $this->balance->amountInCents()) {
                throw new DomainException('Cancelled sessions must have zero balance.');
            }

            if (null !== $this->selectedProductId) {
                throw new DomainException('Cancelled sessions cannot retain a product selection.');
            }
        }

        if (VendingSessionState::Timeout === $this->state) {
            if (SessionCloseReason::Timeout !== $this->closeReason) {
                throw new DomainException('Timeout sessions must include a timeout close reason.');
            }

            if (!$this->insertedCoins->isEmpty()) {
                throw new DomainException('Timeout sessions cannot retain inserted coins.');
            }

            if (null !== $this->changePlan) {
                throw new DomainException('Timeout sessions cannot retain a change plan.');
            }

            if (0 !== $this->balance->amountInCents()) {
                throw new DomainException('Timeout sessions must have zero balance.');
            }

            if (null !== $this->selectedProductId) {
                throw new DomainException('Timeout sessions cannot retain a product selection.');
            }
        }
    }
}
