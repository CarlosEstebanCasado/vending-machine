<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Infrastructure\Command;

use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
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
    private const PRODUCT_IDS = [
        'water' => '11111111-1111-1111-1111-111111111111',
        'soda' => '22222222-2222-2222-2222-222222222222',
        'juice' => '33333333-3333-3333-3333-333333333333',
    ];

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
                status: SlotStatus::Available->value,
                lowStock: false,
                productId: $this->productId('water'),
                productName: 'Water',
                priceCents: 65,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '12',
                capacity: 10,
                recommendedSlotQuantity: 8,
                quantity: 2,
                status: SlotStatus::Available->value,
                lowStock: true,
                productId: $this->productId('soda'),
                productName: 'Soda',
                priceCents: 150,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '13',
                capacity: 12,
                recommendedSlotQuantity: 9,
                quantity: 9,
                status: SlotStatus::Available->value,
                lowStock: false,
                productId: $this->productId('juice'),
                productName: 'Orange Juice',
                priceCents: 100,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '14',
                capacity: 8,
                recommendedSlotQuantity: 6,
                quantity: 4,
                status: SlotStatus::Available->value,
                lowStock: true,
                productId: $this->productId('water'),
                productName: 'Water',
                priceCents: 65,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '15',
                capacity: 10,
                recommendedSlotQuantity: 8,
                quantity: 7,
                status: SlotStatus::Available->value,
                lowStock: false,
                productId: $this->productId('soda'),
                productName: 'Soda',
                priceCents: 150,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '16',
                capacity: 10,
                recommendedSlotQuantity: 8,
                quantity: 6,
                status: SlotStatus::Available->value,
                lowStock: false,
                productId: $this->productId('juice'),
                productName: 'Orange Juice',
                priceCents: 100,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '17',
                capacity: 8,
                recommendedSlotQuantity: 6,
                quantity: 0,
                status: SlotStatus::Available->value,
                lowStock: true,
                productId: null,
                productName: null,
                priceCents: null,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '18',
                capacity: 10,
                recommendedSlotQuantity: 8,
                quantity: 5,
                status: SlotStatus::Available->value,
                lowStock: false,
                productId: $this->productId('water'),
                productName: 'Water',
                priceCents: 65,
            ),
            new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: '19',
                capacity: 12,
                recommendedSlotQuantity: 10,
                quantity: 3,
                status: SlotStatus::Available->value,
                lowStock: true,
                productId: $this->productId('soda'),
                productName: 'Soda',
                priceCents: 150,
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
            available: [
                CoinDenomination::OneDollar->value => 5,
                CoinDenomination::TwentyFiveCents->value => 20,
                CoinDenomination::TenCents->value => 15,
                CoinDenomination::FiveCents->value => 10,
            ],
            reserved: [],
            insufficientChange: false,
            updatedAt: new DateTimeImmutable(),
        );

        $this->documentManager->persist($coins);
    }

    private function productId(string $key): string
    {
        return ProductId::fromString(self::PRODUCT_IDS[$key])->value();
    }
}
