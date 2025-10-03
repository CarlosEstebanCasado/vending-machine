<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\InsertCoin;

use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Session\Application\StartSession\StartSessionResult;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;
use ValueError;

final class InsertCoinCommandHandler
{
    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function handle(InsertCoinCommand $command): StartSessionResult
    {
        /** @var ActiveSessionDocument|null $document */
        $document = $this->documentManager->find(ActiveSessionDocument::class, $command->machineId);

        if (null === $document || null === $document->sessionId()) {
            throw new DomainException('No active session found for this machine.');
        }

        if ($document->sessionId() !== $command->sessionId) {
            throw new DomainException('The provided session id does not match the active session.');
        }

        try {
            $denomination = CoinDenomination::from($command->denomination);
        } catch (ValueError $exception) {
            throw new DomainException('Unsupported coin denomination.', 0, $exception);
        }

        $session = $document->toVendingSession();
        $session->insertCoin($denomination);

        $document->applySession($session);

        $this->documentManager->flush();

        return new StartSessionResult(
            sessionId: $session->id()->value(),
            state: $session->state()->value,
            balanceCents: $session->balance()->amountInCents(),
            insertedCoins: $session->insertedCoins()->toArray(),
            selectedProductId: $session->selectedProductId()?->value(),
            selectedSlotCode: $document->selectedSlotCode(),
        );
    }
}
