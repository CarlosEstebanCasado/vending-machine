<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\Infrastructure\Command;

use App\AdminPanel\User\Infrastructure\Mongo\Document\AdminUserDocument;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use App\VendingMachine\CoinInventory\Infrastructure\Mongo\Document\CoinInventoryDocument;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Inventory\Infrastructure\Mongo\Document\InventorySlotDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\ActiveSessionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\CoinInventoryProjectionDocument;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use App\VendingMachine\Product\Domain\ValueObject\ProductStatus;
use App\VendingMachine\Product\Infrastructure\Mongo\Document\ProductDocument;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function password_hash;

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

        $this->seedProducts();
        $this->seedSlots();
        $this->seedCoinInventory();
        $this->seedAdminUser();

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

        $this->documentManager->createQueryBuilder(AdminUserDocument::class)
            ->remove()
            ->field('email')->equals('admin@vendingmachine.test')
            ->getQuery()
            ->execute();

        $this->documentManager->createQueryBuilder(ProductDocument::class)
            ->remove()
            ->getQuery()
            ->execute();

        $this->documentManager->createQueryBuilder(InventorySlotDocument::class)
            ->remove()
            ->field('machineId')->equals($this->machineId)
            ->getQuery()
            ->execute();
    }

    private function seedProducts(): void
    {
        $products = [
            [
                'id' => $this->productId('water'),
                'sku' => 'WATER-001',
                'name' => 'Water',
                'price' => 65,
                'recommended' => 8,
            ],
            [
                'id' => $this->productId('soda'),
                'sku' => 'SODA-001',
                'name' => 'Soda',
                'price' => 150,
                'recommended' => 8,
            ],
            [
                'id' => $this->productId('juice'),
                'sku' => 'JUICE-001',
                'name' => 'Orange Juice',
                'price' => 100,
                'recommended' => 8,
            ],
        ];

        foreach ($products as $product) {
            $document = new ProductDocument(
                id: $product['id'],
                sku: $product['sku'],
                name: $product['name'],
                priceCents: $product['price'],
                status: ProductStatus::Active->value,
                recommendedSlotQuantity: $product['recommended'],
            );

            $this->documentManager->persist($document);
        }
    }

    private function seedSlots(): void
    {
        $slotDefinitions = [
            ['code' => '11', 'capacity' => 10, 'quantity' => 6, 'recommended' => 8, 'product' => 'water'],
            ['code' => '12', 'capacity' => 10, 'quantity' => 2, 'recommended' => 8, 'product' => 'soda'],
            ['code' => '13', 'capacity' => 12, 'quantity' => 9, 'recommended' => 9, 'product' => 'juice'],
            ['code' => '14', 'capacity' => 8, 'quantity' => 4, 'recommended' => 6, 'product' => 'water'],
            ['code' => '15', 'capacity' => 10, 'quantity' => 7, 'recommended' => 8, 'product' => 'soda'],
            ['code' => '16', 'capacity' => 10, 'quantity' => 6, 'recommended' => 8, 'product' => 'juice'],
            ['code' => '17', 'capacity' => 8, 'quantity' => 0, 'recommended' => 6, 'product' => null],
            ['code' => '18', 'capacity' => 10, 'quantity' => 5, 'recommended' => 8, 'product' => 'water'],
            ['code' => '19', 'capacity' => 12, 'quantity' => 3, 'recommended' => 10, 'product' => 'soda'],
        ];

        foreach ($slotDefinitions as $definition) {
            $productId = null;
            $productName = null;
            $priceCents = null;

            if (null !== $definition['product']) {
                $productId = $this->productId($definition['product']);
                $productName = match ($definition['product']) {
                    'water' => 'Water',
                    'soda' => 'Soda',
                    'juice' => 'Orange Juice',
                    default => null,
                };
                $priceCents = match ($definition['product']) {
                    'water' => 65,
                    'soda' => 150,
                    'juice' => 100,
                    default => null,
                };
            }

            $lowStockThreshold = max(1, (int) floor($definition['recommended'] / 2));
            $lowStock = $definition['quantity'] <= $lowStockThreshold;

            $slotDocument = new SlotProjectionDocument(
                machineId: $this->machineId,
                slotCode: $definition['code'],
                capacity: $definition['capacity'],
                recommendedSlotQuantity: $definition['recommended'],
                quantity: $definition['quantity'],
                status: SlotStatus::Available->value,
                lowStock: $lowStock,
                productId: $productId,
                productName: $productName,
                priceCents: $priceCents,
            );

            $this->documentManager->persist($slotDocument);

            $inventorySlot = new InventorySlotDocument(
                machineId: $this->machineId,
                code: $definition['code'],
                capacity: $definition['capacity'],
                quantity: $definition['quantity'],
                restockThreshold: $lowStockThreshold,
                status: SlotStatus::Available->value,
                productId: $productId,
            );

            $this->documentManager->persist($inventorySlot);
        }
    }

    private function seedCoinInventory(): void
    {
        $available = [
            CoinDenomination::OneDollar->value => 5,
            CoinDenomination::TwentyFiveCents->value => 20,
            CoinDenomination::TenCents->value => 15,
            CoinDenomination::FiveCents->value => 10,
        ];

        $snapshotUpdatedAt = new DateTimeImmutable();

        $coins = new CoinInventoryProjectionDocument(
            machineId: $this->machineId,
            available: $available,
            reserved: [],
            insufficientChange: false,
            updatedAt: $snapshotUpdatedAt,
        );

        $this->documentManager->persist($coins);

        $inventoryDocument = new CoinInventoryDocument(
            machineId: $this->machineId,
            available: $available,
            reserved: [],
            insufficientChange: false,
            updatedAt: $snapshotUpdatedAt,
        );

        $this->documentManager->persist($inventoryDocument);
    }

    private function seedAdminUser(): void
    {
        $adminUser = new AdminUserDocument(
            email: 'admin@vendingmachine.test',
            passwordHash: password_hash('admin-password', PASSWORD_BCRYPT),
            roles: ['admin'],
            active: true,
        );

        $this->documentManager->persist($adminUser);
    }

    private function productId(string $key): string
    {
        return ProductId::fromString(self::PRODUCT_IDS[$key])->value();
    }
}
