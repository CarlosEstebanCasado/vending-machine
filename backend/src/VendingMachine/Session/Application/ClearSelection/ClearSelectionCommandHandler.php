<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\ClearSelection;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Session\Application\StartSession\StartSessionResult;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;

final class ClearSelectionCommandHandler
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function handle(ClearSelectionCommand $command): StartSessionResult
    {
        /** @var ActiveSessionDocument|null $document */
        $document = $this->documentManager->find(ActiveSessionDocument::class, $command->machineId);

        if (null === $document || null === $document->sessionId()) {
            throw new DomainException('No active session found for this machine.');
        }

        if ($document->sessionId() !== $command->sessionId) {
            throw new DomainException('The provided session id does not match the active session.');
        }

        $session = $document->toVendingSession();
        $session->clearSelection();

        $document->applySession($session, null);

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
