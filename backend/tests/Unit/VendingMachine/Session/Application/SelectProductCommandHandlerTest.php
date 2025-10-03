<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Application;

use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Session\Application\SelectProduct\SelectProductCommand;
use App\VendingMachine\Session\Application\SelectProduct\SelectProductCommandHandler;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;
use Doctrine\ODM\MongoDB\DocumentManager;
use DomainException;
use PHPUnit\Framework\TestCase;

final class SelectProductCommandHandlerTest extends TestCase
{
    public function testItAssignsSelectedProductToActiveSession(): void
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

        $handler = new SelectProductCommandHandler($documentManager);

        $result = $handler->handle(new SelectProductCommand('machine-1', 'session-1', 'product-1', 'A1'));

        self::assertSame('product-1', $document->selectedProductId());
        self::assertSame('product-1', $result->selectedProductId);
        self::assertSame('A1', $document->selectedSlotCode());
        self::assertSame('A1', $result->selectedSlotCode);
    }

    public function testItFailsWhenActiveSessionIsMissing(): void
    {
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('find')
            ->with(ActiveSessionDocument::class, 'machine-1')
            ->willReturn(null);

        $handler = new SelectProductCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No active session found for this machine.');

        $handler->handle(new SelectProductCommand('machine-1', 'session-1', 'product-1', 'A1'));
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

        $handler = new SelectProductCommandHandler($documentManager);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The provided session id does not match the active session.');

        $handler->handle(new SelectProductCommand('machine-1', 'session-1', 'product-1', 'A1'));
    }
}
