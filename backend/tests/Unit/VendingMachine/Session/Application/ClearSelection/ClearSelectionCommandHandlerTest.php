<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Application\ClearSelection;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Session\Application\ClearSelection\ClearSelectionCommand;
use App\VendingMachine\Session\Application\ClearSelection\ClearSelectionCommandHandler;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ClearSelectionCommandHandlerTest extends TestCase
{
    public function testItClearsSelectionInActiveSession(): void
    {
        $document = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
            state: VendingSessionState::Collecting->value,
            balanceCents: 100,
            insertedCoins: [100 => 1],
            selectedProductId: 'product-1',
            selectedSlotCode: 'A1',
            changePlan: null,
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn($document);

        $documentManager->expects(self::once())
            ->method('flush');

        $handler = new ClearSelectionCommandHandler($documentManager);

        $result = $handler->handle(new ClearSelectionCommand('machine-1', 'session-1'));

        self::assertNull($document->selectedProductId());
        self::assertNull($document->selectedSlotCode());
        self::assertNull($result->selectedProductId);
        self::assertNull($result->selectedSlotCode);
    }

    public function testItFailsWhenActiveSessionIsMissing(): void
    {
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn(null);

        $handler = new ClearSelectionCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No active session found for this machine.');

        $handler->handle(new ClearSelectionCommand('machine-1', 'session-1'));
    }

    public function testItFailsWhenSessionIdDoesNotMatch(): void
    {
        $document = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-2',
            state: VendingSessionState::Collecting->value,
            balanceCents: 0,
            insertedCoins: [],
            selectedProductId: null,
            selectedSlotCode: null,
            changePlan: null,
        );

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn($document);

        $handler = new ClearSelectionCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The provided session id does not match the active session.');

        $handler->handle(new ClearSelectionCommand('machine-1', 'session-1'));
    }
}
