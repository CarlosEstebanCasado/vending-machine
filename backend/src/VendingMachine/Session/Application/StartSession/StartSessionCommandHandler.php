<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\StartSession;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionId;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use App\VendingMachine\Session\Domain\VendingSession;
use Doctrine\ODM\MongoDB\DocumentManager;

final class StartSessionCommandHandler
{
    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function handle(StartSessionCommand $command): StartSessionResult
    {
        $session = VendingSession::start(VendingSessionId::generate());

        /** @var ActiveSessionDocument|null $document */
        $document = $this->documentManager->find(ActiveSessionDocument::class, $command->machineId);

        if (null === $document) {
            $document = new ActiveSessionDocument(
                machineId: $command->machineId,
                sessionId: null,
                state: VendingSessionState::Collecting->value,
                balanceCents: 0,
                insertedCoins: [],
                selectedProductId: null,
                selectedSlotCode: null,
                changePlan: null,
            );

            $this->documentManager->persist($document);
        }

        $document->applySession($session);

        $this->documentManager->flush();

        return new StartSessionResult(
            sessionId: $session->id()->value(),
            state: $session->state()->value,
            balanceCents: $session->balance()->amountInCents(),
            insertedCoins: $session->insertedCoins()->toArray(),
            selectedProductId: $session->selectedProductId()?->value(),
            selectedSlotCode: null,
        );
    }
}
