<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Infrastructure\Command;

use App\VendingMachine\Machine\Infrastructure\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Document\CoinInventoryProjectionDocument;
use App\VendingMachine\Machine\Infrastructure\Document\SlotProjectionDocument;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-machine-state', description: 'Seed initial vending machine projections in MongoDB')]
final class SeedMachineStateCommand extends Command
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly string $machineId,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->purgeExistingData();

        $this->seedSlots();
        $this->seedCoinInventory();
        $this->seedSession();

        $this->documentManager->flush();

        $io->success(sprintf('Seeded machine state for machine id "%s"', $this->machineId));

        return Command::SUCCESS;
    }

    private function purgeExistingData(): void
    {
        $this->documentManager->createQueryBuilder(SlotProjectionDocument::class)
            ->remove()
            ->field('machineId')->equals($this->machineId)
            ->getQuery()
            ->execute();

        $this->documentManager->createQueryBuilder(CoinInventoryProjectionDocument::class)
            ->remove()
            ->field('machineId')->equals($this->machineId)
            ->getQuery()
            ->execute();

        $this->documentManager->createQueryBuilder(ActiveSessionDocument::class)
            ->remove()
            ->field('_id')->equals($this->machineId)
            ->getQuery()
            ->execute();
    }

    private function seedSlots(): void
    {
        $slots = [
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '11',
                capacity: 10,
                recommendedSlotQuantity: 8,
                quantity: 6,
                status: 'available',
                lowStock: false,
                productId: 'prod-water',
                productName: 'Water',
                priceCents: 150,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '12',
                capacity: 10,
                recommendedSlotQuantity: 8,
                quantity: 1,
                status: 'available',
                lowStock: true,
                productId: 'prod-soda',
                productName: 'Soda',
                priceCents: 200,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '21',
                capacity: 10,
                recommendedSlotQuantity: 8,
                quantity: 0,
                status: 'disabled',
                lowStock: true,
                productId: null,
                productName: null,
                priceCents: null,
            ),
        ];

        foreach ($slots as $slot) {
            $this->documentManager->persist($slot);
        }
    }

    private function seedCoinInventory(): void
    {
        $coins = new CoinInventoryProjectionDocument(
            machineId: $this->machineId,
            available: [100 => 5, 25 => 20, 10 => 15, 5 => 10],
            reserved: [25 => 2, 10 => 1],
            insufficientChange: false,
            updatedAt: new DateTimeImmutable(),
        );

        $this->documentManager->persist($coins);
    }

    private function seedSession(): void
    {
        $session = new ActiveSessionDocument(
            machineId: $this->machineId,
            sessionId: null,
            state: 'collecting',
            balanceCents: 0,
            insertedCoins: [],
            selectedProductId: null,
            changePlan: null,
            updatedAt: new DateTimeImmutable(),
        );

        $this->documentManager->persist($session);
    }
}
