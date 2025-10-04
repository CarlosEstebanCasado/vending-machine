<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\CoinInventory\Application\AdjustInventory;

use App\VendingMachine\CoinInventory\Application\AdjustInventory\AdjustCoinInventoryCommand;
use App\VendingMachine\CoinInventory\Application\AdjustInventory\AdjustCoinInventoryCommandHandler;
use App\VendingMachine\CoinInventory\Application\AdjustInventory\AdjustCoinInventoryOperation;
use App\VendingMachine\CoinInventory\Domain\CoinInventoryRepository;
use App\VendingMachine\CoinInventory\Domain\CoinInventorySnapshot;
use App\VendingMachine\CoinInventory\Domain\Service\ChangeAvailabilityChecker;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use PHPUnit\Framework\TestCase;

final class AdjustCoinInventoryCommandHandlerTest extends TestCase
{
    public function testWithdrawMarksInsufficientChange(): void
    {
        $repository = $this->createMock(DocumentRepository::class);
        $projection = new CoinInventoryProjectionDocument('machine-1', [25 => 1], [], false, new DateTimeImmutable('-1 minute'));
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['machineId' => 'machine-1'])
            ->willReturn($projection);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(CoinInventoryProjectionDocument::class)
            ->willReturn($repository);
        $documentManager->expects(self::once())
            ->method('flush');

        $coinRepository = $this->createMock(CoinInventoryRepository::class);
        $coinRepository->expects(self::once())
            ->method('find')
            ->with('machine-1')
            ->willReturn(new CoinInventorySnapshot('machine-1', [25 => 1], [], false, new DateTimeImmutable('-5 minutes')));
        $coinRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (CoinInventorySnapshot $snapshot): bool {
                return 'machine-1' === $snapshot->machineId
                    && ([] === $snapshot->available)
                    && true === $snapshot->insufficientChange;
            }));

        $handler = new AdjustCoinInventoryCommandHandler(
            $coinRepository,
            $documentManager,
            new ChangeAvailabilityChecker(),
        );

        $command = new AdjustCoinInventoryCommand('machine-1', AdjustCoinInventoryOperation::Withdraw, [25 => 1]);

        $handler->handle($command);

        self::assertTrue($projection->insufficientChange());
    }

    public function testDepositClearsInsufficientChangeFlag(): void
    {
        $repository = $this->createMock(DocumentRepository::class);
        $projection = new CoinInventoryProjectionDocument('machine-1', [], [], true, new DateTimeImmutable('-10 minutes'));
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['machineId' => 'machine-1'])
            ->willReturn($projection);

        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects(self::once())
            ->method('getRepository')
            ->with(CoinInventoryProjectionDocument::class)
            ->willReturn($repository);
        $documentManager->expects(self::once())
            ->method('flush');

        $coinRepository = $this->createMock(CoinInventoryRepository::class);
        $coinRepository->expects(self::once())
            ->method('find')
            ->with('machine-1')
            ->willReturn(new CoinInventorySnapshot('machine-1', [], [], true, new DateTimeImmutable('-20 minutes')));
        $coinRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (CoinInventorySnapshot $snapshot): bool {
                return 'machine-1' === $snapshot->machineId
                    && false === $snapshot->insufficientChange
                    && 2 === ($snapshot->available[10] ?? 0)
                    && 2 === ($snapshot->available[5] ?? 0);
            }));

        $handler = new AdjustCoinInventoryCommandHandler(
            $coinRepository,
            $documentManager,
            new ChangeAvailabilityChecker(),
        );

        $command = new AdjustCoinInventoryCommand('machine-1', AdjustCoinInventoryOperation::Deposit, [10 => 2, 5 => 2]);

        $handler->handle($command);

        self::assertFalse($projection->insufficientChange());
    }
}
