<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Application\AdjustInventory;

use App\VendingMachine\CoinInventory\Application\GetInventory\CoinInventoryNotFound;
use App\VendingMachine\CoinInventory\Domain\CoinInventory;
use App\VendingMachine\CoinInventory\Domain\CoinInventoryRepository;
use App\VendingMachine\CoinInventory\Domain\CoinInventorySnapshot;
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
    ) {
    }

    public function __invoke(AdjustCoinInventoryCommand $command): void
    {
        $adjustmentBundle = $this->buildBundle($command->denominations);

        /** @var CoinInventoryProjectionDocument|null $projection */
        $projection = $this->documentManager
            ->getRepository(CoinInventoryProjectionDocument::class)
            ->findOneBy(['machineId' => $command->machineId]);

        if (null === $projection) {
            throw new CoinInventoryNotFound(sprintf('Coin inventory not found for machine "%s".', $command->machineId));
        }

        $inventorySnapshot = $this->coinInventoryRepository->find($command->machineId)
            ?? new CoinInventorySnapshot($command->machineId, [], [], new DateTimeImmutable());

        $availableBundle = CoinBundle::fromArray($inventorySnapshot->available);
        $reservedBundle = CoinBundle::fromArray($inventorySnapshot->reserved);

        $inventory = CoinInventory::restore($availableBundle, $reservedBundle);

        match ($command->operation) {
            AdjustCoinInventoryOperation::Deposit => $inventory->deposit($adjustmentBundle),
            AdjustCoinInventoryOperation::Withdraw => $this->withdraw($inventory, $adjustmentBundle),
        };

        $this->coinInventoryRepository->save(new CoinInventorySnapshot(
            machineId: $command->machineId,
            available: $inventory->availableCoins()->toArray(),
            reserved: $inventory->reservedCoins()->toArray(),
            updatedAt: new DateTimeImmutable(),
        ));

        $projection->applyInventory($inventory, $projection->insufficientChange());
        $this->documentManager->flush();
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
}
