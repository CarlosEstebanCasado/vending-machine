<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Infrastructure\Mongo\Document;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionId;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use App\VendingMachine\Session\Domain\VendingSession;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use DomainException;

#[ODM\Document(collection: 'machine_sessions')]
class ActiveSessionDocument
{
    #[ODM\Id(strategy: 'NONE', type: 'string')]
    private string $machineId;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $sessionId = null;

    #[ODM\Field(type: 'string')]
    private string $state = VendingSessionState::Collecting->value;

    #[ODM\Field(type: 'int')]
    private int $balanceCents = 0;

    #[ODM\Field(type: 'hash')]
    private array $insertedCoins = [];

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $selectedProductId = null;

    #[ODM\Field(type: 'hash', nullable: true)]
    private ?array $changePlan = null;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<int, int>      $insertedCoins
     * @param array<int, int>|null $changePlan
     */
    public function __construct(
        string $machineId,
        ?string $sessionId,
        string $state,
        int $balanceCents,
        array $insertedCoins,
        ?string $selectedProductId,
        ?array $changePlan,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->machineId = $machineId;
        $this->sessionId = $sessionId;
        $this->state = $state;
        $this->balanceCents = $balanceCents;
        $this->insertedCoins = $this->normalizeCoinMap($insertedCoins);
        $this->selectedProductId = $selectedProductId;
        $this->changePlan = null === $changePlan ? null : $this->normalizeCoinMap($changePlan);
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function machineId(): string
    {
        return $this->machineId;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function balanceCents(): int
    {
        return $this->balanceCents;
    }

    /**
     * @return array<int, int>
     */
    public function insertedCoins(): array
    {
        return $this->normalizeCoinMap($this->insertedCoins);
    }

    public function selectedProductId(): ?string
    {
        return $this->selectedProductId;
    }

    /**
     * @return array<int, int>|null
     */
    public function changePlan(): ?array
    {
        return null === $this->changePlan ? null : $this->normalizeCoinMap($this->changePlan);
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function applySession(VendingSession $session): void
    {
        $this->sessionId = $session->id()->value();
        $this->state = $session->state()->value;
        $this->balanceCents = $session->balance()->amountInCents();
        $this->insertedCoins = $this->normalizeCoinMap($session->insertedCoins()->toArray());
        $this->selectedProductId = $session->selectedProductId()?->value();
        $this->changePlan = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toVendingSession(): VendingSession
    {
        if (null === $this->sessionId) {
            throw new DomainException('Cannot restore session without an active session id.');
        }

        return VendingSession::restore(
            VendingSessionId::fromString($this->sessionId),
            VendingSessionState::from($this->state),
            CoinBundle::fromArray($this->insertedCoins()),
            Money::fromCents($this->balanceCents),
            null === $this->selectedProductId ? null : ProductId::fromString($this->selectedProductId),
            null === $this->changePlan ? null : CoinBundle::fromArray($this->changePlan()),
            null,
        );
    }

    /**
     * @param array<int|string, int> $coins
     *
     * @return array<int, int>
     */
    private function normalizeCoinMap(array $coins): array
    {
        $normalized = [];

        foreach ($coins as $denomination => $quantity) {
            $normalized[(int) $denomination] = (int) $quantity;
        }

        return array_filter(
            $normalized,
            static fn (int $quantity): bool => $quantity > 0
        );
    }
}
