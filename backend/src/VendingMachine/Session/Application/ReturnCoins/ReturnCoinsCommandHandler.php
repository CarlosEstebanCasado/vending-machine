<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\ReturnCoins;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;

final class ReturnCoinsCommandHandler
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function handle(ReturnCoinsCommand $command): ReturnCoinsResult
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
        $returnedCoins = $session->returnCoins();

        $document->applySession($session);

        $this->documentManager->flush();

        return new ReturnCoinsResult(
            sessionId: $session->id()->value(),
            state: $session->state()->value,
            balanceCents: $session->balance()->amountInCents(),
            insertedCoins: $session->insertedCoins()->toArray(),
            selectedProductId: $session->selectedProductId()?->value(),
            selectedSlotCode: $document->selectedSlotCode(),
            returnedCoins: $returnedCoins->toArray(),
        );
    }
}
