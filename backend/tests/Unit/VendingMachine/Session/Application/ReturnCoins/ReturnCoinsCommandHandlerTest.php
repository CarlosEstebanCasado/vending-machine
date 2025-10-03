<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Application\ReturnCoins;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Session\Application\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Session\Application\ReturnCoins\ReturnCoinsCommandHandler;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ReturnCoinsCommandHandlerTest extends TestCase
{
    public function testItReturnsInsertedCoinsAndResetsSession(): void
    {
        $document = new ActiveSessionDocument(
            machineId: 'machine-1',
            sessionId: 'session-1',
            state: VendingSessionState::Collecting->value,
            balanceCents: 125,
            insertedCoins: [100 => 1, 25 => 1],
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

        $handler = new ReturnCoinsCommandHandler($documentManager);

        $result = $handler->handle(new ReturnCoinsCommand('machine-1', 'session-1'));

        self::assertSame(1, $result->returnedCoins[100] ?? null);
        self::assertSame(1, $result->returnedCoins[25] ?? null);
        self::assertSame(0, $result->balanceCents);
        self::assertNull($result->selectedProductId);
        self::assertNull($result->selectedSlotCode);
        self::assertSame([], $result->insertedCoins);
    }

    public function testItFailsWhenActiveSessionIsMissing(): void
    {
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn(null);

        $handler = new ReturnCoinsCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No active session found for this machine.');

        $handler->handle(new ReturnCoinsCommand('machine-1', 'session-1'));
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

        $handler = new ReturnCoinsCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The provided session id does not match the active session.');

        $handler->handle(new ReturnCoinsCommand('machine-1', 'session-1'));
    }
}
