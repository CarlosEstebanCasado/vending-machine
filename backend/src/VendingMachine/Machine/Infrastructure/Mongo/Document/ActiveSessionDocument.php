<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Infrastructure\Mongo\Document;

use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use App\VendingMachine\Session\Domain\VendingSession;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

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
        $this->insertedCoins = array_map('intval', $insertedCoins);
        $this->selectedProductId = $selectedProductId;
        $this->changePlan = null === $changePlan ? null : array_map('intval', $changePlan);
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
        return array_map('intval', $this->insertedCoins);
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
        return null === $this->changePlan ? null : array_map('intval', $this->changePlan);
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
        $this->insertedCoins = array_map('intval', $session->insertedCoins()->toArray());
        $this->selectedProductId = $session->selectedProductId()?->value();
        $this->changePlan = null;
        $this->updatedAt = new DateTimeImmutable();
    }
}
