<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Application;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Session\Application\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Session\Application\InsertCoin\InsertCoinCommandHandler;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;
use PHPUnit\Framework\TestCase;

final class InsertCoinCommandHandlerTest extends TestCase
{
    public function testItAddsCoinToActiveSession(): void
    {
        $document = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
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

        $documentManager->expects(self::once())
            ->method('flush');

        $handler = new InsertCoinCommandHandler($documentManager);

        $result = $handler->handle(new InsertCoinCommand('machine-1', 'session-1', 100));

        self::assertSame(100, $result->balanceCents);
        self::assertSame([100 => 1], $result->insertedCoins);
        self::assertSame($result->insertedCoins, $document->insertedCoins());
        self::assertNull($result->selectedSlotCode);
        self::assertNull($document->selectedSlotCode());
    }

    public function testItFailsWhenActiveSessionIsMissing(): void
    {
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn(null);

        $handler = new InsertCoinCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No active session found for this machine.');

        $handler->handle(new InsertCoinCommand('machine-1', 'session-1', 100));
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

        $handler = new InsertCoinCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The provided session id does not match the active session.');

        $handler->handle(new InsertCoinCommand('machine-1', 'session-1', 100));
    }

    public function testItFailsWithUnsupportedDenomination(): void
    {
        $document = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
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

        $handler = new InsertCoinCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unsupported coin denomination.');

        $handler->handle(new InsertCoinCommand('machine-1', 'session-1', 3));
    }
}
