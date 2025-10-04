<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\AdminPanel\Inventory\Application\AdjustSlotInventory\AdminAdjustSlotInventoryCommandHandler;
use App\AdminPanel\Inventory\Application\GetSlots\AdminGetSlotsQueryHandler;
use App\AdminPanel\Inventory\UI\Http\Controller\AdjustSlotInventoryController;
use App\AdminPanel\Inventory\UI\Http\Controller\GetSlotInventoryController;
use App\AdminPanel\User\Infrastructure\Mongo\Document\AdminUserDocument;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Inventory\Infrastructure\Mongo\Document\InventorySlotDocument;
use App\VendingMachine\Inventory\Infrastructure\Mongo\MongoInventorySlotRepository;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Product\Infrastructure\Mongo\Document\ProductDocument;
use App\VendingMachine\Product\Infrastructure\Mongo\MongoProductRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Exception\ConnectionTimeoutException;

use function json_decode;
use function json_encode;

final class AdminInventoryControllerTest extends KernelTestCase
{
    private DocumentManager $documentManager;
    private string $machineId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->documentManager = $container->get(DocumentManager::class);
        $this->machineId = $container->get('parameter_bag')->get('app.machine_id');

        $this->ensureMongoAvailable();
        $this->purgeCollections();
    }

    protected function tearDown(): void
    {
        $this->purgeCollections();
        parent::tearDown();
    }

    private function ensureMongoAvailable(): void
    {
        try {
            $client = $this->documentManager->getClient();
            $database = $this->documentManager->getConfiguration()->getDefaultDB();
            $client->selectDatabase($database)->command(['ping' => 1]);
        } catch (ConnectionTimeoutException|ConnectionException $exception) {
            self::markTestSkipped('MongoDB connection unavailable: ' . $exception->getMessage());
        }
    }

    public function testGetSlotsReturnsInventoryData(): void
    {
        $product = new ProductDocument('product-1', 'SKU-001', 'Water', 125, 'active', 8);
        $this->documentManager->persist($product);

        $slot = new InventorySlotDocument(
            machineId: $this->machineId,
            code: '11',
            capacity: 10,
            quantity: 4,
            restockThreshold: 2,
            status: SlotStatus::Available->value,
            productId: 'product-1',
        );
        $this->documentManager->persist($slot);
        $this->documentManager->flush();

        $controller = $this->createGetSlotsController();

        $response = $controller(Request::create('/api/admin/slots', 'GET', ['machineId' => $this->machineId]));

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($this->machineId, $payload['machineId']);
        self::assertCount(1, $payload['slots']);
        $slotPayload = $payload['slots'][0];
        self::assertSame('11', $slotPayload['code']);
        self::assertSame('Water', $slotPayload['productName']);
    }

    public function testAdjustSlotInventoryRestockUpdatesSlotAndProjection(): void
    {
        $product = new ProductDocument('product-1', 'SKU-001', 'Water', 125, 'active', 8);
        $this->documentManager->persist($product);

        $slot = new InventorySlotDocument(
            machineId: $this->machineId,
            code: '11',
            capacity: 10,
            quantity: 0,
            restockThreshold: 2,
            status: SlotStatus::Disabled->value,
            productId: null,
        );
        $this->documentManager->persist($slot);

        $projection = new SlotProjectionDocument(
            machineId: $this->machineId,
            slotCode: '11',
            capacity: 10,
            recommendedSlotQuantity: 8,
            quantity: 0,
            status: SlotStatus::Disabled->value,
            lowStock: true,
            productId: null,
            productName: null,
            priceCents: null,
        );
        $this->documentManager->persist($projection);
        $this->documentManager->flush();

        $controller = $this->createAdjustSlotInventoryController();

        $response = $controller(Request::create(
            '/api/admin/slots/stock',
            'POST',
            content: json_encode([
                'machineId' => $this->machineId,
                'slotCode' => '11',
                'operation' => 'restock',
                'quantity' => 3,
                'productId' => 'product-1',
            ], JSON_THROW_ON_ERROR),
        ));

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(JsonResponse::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->documentManager->clear();

        /** @var InventorySlotDocument|null $updatedSlot */
        $updatedSlot = $this->documentManager->find(InventorySlotDocument::class, sprintf('%s-%s', $this->machineId, '11'));
        self::assertNotNull($updatedSlot);
        self::assertSame(3, $updatedSlot->quantity());
        self::assertSame('product-1', $updatedSlot->productId());
        self::assertSame(SlotStatus::Available->value, $updatedSlot->status());

        /** @var SlotProjectionDocument|null $updatedProjection */
        $updatedProjection = $this->documentManager->find(SlotProjectionDocument::class, sprintf('%s-%s', $this->machineId, '11'));
        self::assertNotNull($updatedProjection);
        self::assertSame(3, $updatedProjection->quantity());
        self::assertSame('product-1', $updatedProjection->productId());
        self::assertSame('Water', $updatedProjection->productName());
    }

    private function purgeCollections(): void
    {
        try {
            foreach ([
                InventorySlotDocument::class,
                SlotProjectionDocument::class,
                ProductDocument::class,
                AdminUserDocument::class,
            ] as $documentClass) {
                $this->documentManager->createQueryBuilder($documentClass)
                    ->remove()
                    ->getQuery()
                    ->execute();
            }
        } catch (ConnectionTimeoutException|ConnectionException) {
            // Mongo is unavailable (e.g. CI without Mongo service); purge can be skipped.
        }
    }

    private function createGetSlotsController(): GetSlotInventoryController
    {
        $slotRepository = new MongoInventorySlotRepository($this->documentManager);
        $productRepository = new MongoProductRepository($this->documentManager);

        return new GetSlotInventoryController(
            new AdminGetSlotsQueryHandler($slotRepository, $productRepository),
            $this->machineId,
        );
    }

    private function createAdjustSlotInventoryController(): AdjustSlotInventoryController
    {
        $slotRepository = new MongoInventorySlotRepository($this->documentManager);
        $productRepository = new MongoProductRepository($this->documentManager);

        return new AdjustSlotInventoryController(
            new AdminAdjustSlotInventoryCommandHandler($slotRepository, $productRepository, $this->documentManager),
            $this->machineId,
        );
    }
}
