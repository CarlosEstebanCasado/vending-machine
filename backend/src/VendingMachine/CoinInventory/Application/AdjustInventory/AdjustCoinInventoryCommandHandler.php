<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Application\AdjustInventory;

use App\VendingMachine\CoinInventory\Application\GetInventory\CoinInventoryNotFound;
use App\VendingMachine\CoinInventory\Domain\CoinInventory;
use App\VendingMachine\CoinInventory\Domain\CoinInventoryRepository;
use App\VendingMachine\CoinInventory\Domain\CoinInventorySnapshot;
use App\VendingMachine\CoinInventory\Domain\Service\ChangeAvailabilityChecker;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use InvalidArgumentException;

final class AdjustCoinInventoryCommandHandler
{
    public function __construct(
        private readonly CoinInventoryRepository $coinInventoryRepository,
        private readonly DocumentManager $documentManager,
        private readonly ChangeAvailabilityChecker $changeAvailabilityChecker,
    ) {
    }

    public function handle(AdjustCoinInventoryCommand $command): void
    {
        $adjustment = $this->buildBundle($command->denominations);
        $projection = $this->findProjection($command->machineId);
        $inventory = $this->rebuildInventory($command->machineId);

        $this->applyAdjustment($inventory, $adjustment, $command->operation);

        $insufficientChange = !$this->changeAvailabilityChecker->isChangeSufficient($inventory);

        $this->persistSnapshot($command->machineId, $inventory, $insufficientChange);
        $this->updateProjection($projection, $inventory, $insufficientChange);
    }

    /**
     * @param array<int, int> $input
     */
    private function buildBundle(array $input): CoinBundle
    {
        if ([] === $input) {
            throw new InvalidArgumentException('At least one denomination must be provided.');
        }

        $normalized = [];

        foreach ($input as $denomination => $quantity) {
            $denomination = (int) $denomination;
            $quantity = (int) $quantity;

            if ($quantity < 0) {
                throw new InvalidArgumentException('Quantities must be positive integers.');
            }

            if (null === CoinDenomination::tryFrom($denomination)) {
                throw new InvalidArgumentException(sprintf('Denomination %d is not supported.', $denomination));
            }

            $normalized[$denomination] = $quantity;
        }

        return CoinBundle::fromArray($normalized);
    }

    private function withdraw(CoinInventory $inventory, CoinBundle $bundle): void
    {
        if (!$inventory->availableCoins()->includesAtLeast($bundle)) {
            throw new InvalidArgumentException('Cannot withdraw more coins than available.');
        }

        $inventory->withdraw($bundle);
    }

    private function findProjection(string $machineId): CoinInventoryProjectionDocument
    {
        /** @var CoinInventoryProjectionDocument|null $projection */
        $projection = $this->documentManager
            ->getRepository(CoinInventoryProjectionDocument::class)
            ->findOneBy(['machineId' => $machineId]);

        if (null === $projection) {
            throw new CoinInventoryNotFound(sprintf('Coin inventory not found for machine "%s".', $machineId));
        }

        return $projection;
    }

    private function rebuildInventory(string $machineId): CoinInventory
    {
        $snapshot = $this->coinInventoryRepository->find($machineId)
            ?? new CoinInventorySnapshot($machineId, [], [], false, new DateTimeImmutable());

        return CoinInventory::restore(
            CoinBundle::fromArray($snapshot->available),
            CoinBundle::fromArray($snapshot->reserved),
        );
    }

    private function applyAdjustment(
        CoinInventory $inventory,
        CoinBundle $bundle,
        AdjustCoinInventoryOperation $operation,
    ): void {
        match ($operation) {
            AdjustCoinInventoryOperation::Deposit => $inventory->deposit($bundle),
            AdjustCoinInventoryOperation::Withdraw => $this->withdraw($inventory, $bundle),
        };
    }

    private function persistSnapshot(string $machineId, CoinInventory $inventory, bool $insufficientChange): void
    {
        $this->coinInventoryRepository->save(new CoinInventorySnapshot(
            machineId: $machineId,
            available: $inventory->availableCoins()->toArray(),
            reserved: $inventory->reservedCoins()->toArray(),
            insufficientChange: $insufficientChange,
            updatedAt: new DateTimeImmutable(),
        ));
    }

    private function updateProjection(
        CoinInventoryProjectionDocument $projection,
        CoinInventory $inventory,
        bool $insufficientChange,
    ): void {
        $projection->applyInventory($inventory, $insufficientChange);
        $this->documentManager->flush();
    }
}
