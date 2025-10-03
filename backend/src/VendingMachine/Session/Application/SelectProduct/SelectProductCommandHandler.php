<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Application\SelectProduct;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Session\Application\StartSession\StartSessionResult;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;

final class SelectProductCommandHandler
{
    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function handle(SelectProductCommand $command): StartSessionResult
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
        $session->selectProduct(ProductId::fromString($command->productId));

        $document->applySession($session);

        $this->documentManager->flush();

        return new StartSessionResult(
            sessionId: $session->id()->value(),
            state: $session->state()->value,
            balanceCents: $session->balance()->amountInCents(),
            insertedCoins: $session->insertedCoins()->toArray(),
            selectedProductId: $session->selectedProductId()?->value(),
        );
    }
}
