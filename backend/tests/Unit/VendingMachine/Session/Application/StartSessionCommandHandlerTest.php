<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Application;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Session\Application\StartSession\StartSessionCommand;
use App\VendingMachine\Session\Application\StartSession\StartSessionCommandHandler;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use Doctrine\ODM\MongoDB\DocumentManager;
use PHPUnit\Framework\TestCase;

final class StartSessionCommandHandlerTest extends TestCase
{
    public function testItCreatesDocumentWhenMissing(): void
    {
        $persistedDocument = null;

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn(null);

        $documentManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (object $document) use (&$persistedDocument): bool {
                $persistedDocument = $document;

                return $document instanceof ActiveSessionDocument;
            }));

        $documentManager->expects(self::once())
            ->method('flush');

        $handler = new StartSessionCommandHandler($documentManager);

        $result = $handler->handle(new StartSessionCommand('machine-1'));

        self::assertNotNull($persistedDocument);
        self::assertInstanceOf(ActiveSessionDocument::class, $persistedDocument);
        self::assertSame($result->sessionId, $persistedDocument->sessionId());
        self::assertSame($result->state, $persistedDocument->state());
        self::assertSame($result->balanceCents, $persistedDocument->balanceCents());
        self::assertSame($result->insertedCoins, $persistedDocument->insertedCoins());
        self::assertSame($result->selectedProductId, $persistedDocument->selectedProductId());
    }

    public function testItUpdatesExistingDocument(): void
    {
        $existingDocument = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: null,
            state: VendingSessionState::Collecting->value,
            balanceCents: 0,
            insertedCoins: [],
            selectedProductId: null,
            changePlan: null,
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn($existingDocument);

        $documentManager->expects(self::never())
            ->method('persist');

        $documentManager->expects(self::once())
            ->method('flush');

        $handler = new StartSessionCommandHandler($documentManager);

        $result = $handler->handle(new StartSessionCommand('machine-1'));

        self::assertSame($result->sessionId, $existingDocument->sessionId());
        self::assertSame($result->state, $existingDocument->state());
        self::assertSame($result->balanceCents, $existingDocument->balanceCents());
        self::assertSame($result->insertedCoins, $existingDocument->insertedCoins());
        self::assertSame($result->selectedProductId, $existingDocument->selectedProductId());
    }
}
