<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Application\AdjustSlotInventory;

use App\VendingMachine\Inventory\Domain\InventorySlot;
use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Machine\Infrastructure\Mongo\Document\SlotProjectionDocument;
use App\VendingMachine\Product\Domain\Product;
use App\VendingMachine\Product\Domain\ProductRepository;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use Doctrine\ODM\MongoDB\DocumentManager;
use InvalidArgumentException;

final class AdminAdjustSlotInventoryCommandHandler
{
    public function __construct(
        private readonly InventorySlotRepository $slotRepository,
        private readonly ProductRepository $productRepository,
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function handle(AdminAdjustSlotInventoryCommand $command): void
    {
        $this->assertQuantityPositive($command);

        $slot = $this->findSlot($command->machineId, $command->slotCode);
        $this->assertSlotIsAdjustable($slot, $command->operation);

        $product = AdjustSlotInventoryOperation::Restock === $command->operation
            ? $this->restockSlot($slot, $command)
            : $this->withdrawFromSlot($slot, $command->quantity);

        $product ??= $this->resolveProductForProjection($slot);

        $this->slotRepository->save($slot, $command->machineId);
        $this->syncProjection($command->machineId, $command->slotCode, $slot, $product);
    }

    private function assertQuantityPositive(AdminAdjustSlotInventoryCommand $command): void
    {
        if ($command->quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }
    }

    private function findSlot(string $machineId, string $slotCode): InventorySlot
    {
        $slot = $this->slotRepository->findByMachineAndCode($machineId, SlotCode::fromString($slotCode));

        if (null === $slot) {
            throw new InvalidArgumentException(sprintf('Slot "%s" not found for machine "%s".', $slotCode, $machineId));
        }

        return $slot;
    }

    private function assertSlotIsAdjustable(InventorySlot $slot, AdjustSlotInventoryOperation $operation): void
    {
        if (!$slot->status()->isReserved()) {
            return;
        }

        $message = AdjustSlotInventoryOperation::Restock === $operation
            ? 'Slot cannot be restocked while it is reserved by an active session.'
            : 'Slot cannot be adjusted while it is reserved by an active session.';

        throw new InvalidArgumentException($message);
    }

    private function restockSlot(InventorySlot $slot, AdminAdjustSlotInventoryCommand $command): Product
    {
        if (null === $command->productId) {
            throw new InvalidArgumentException('Product id must be provided when restocking a slot.');
        }

        $productId = ProductId::fromString($command->productId);
        $product = $this->productRepository->find($productId);

        if (null === $product) {
            throw new InvalidArgumentException('Product not found.');
        }

        if (!$slot->hasProductAssigned()) {
            $slot->assignProduct($productId);
        } elseif (!$slot->productId()?->equals($productId)) {
            throw new InvalidArgumentException('Slot already assigned to a different product.');
        }

        $slot->restock($command->quantity);

        return $product;
    }

    private function withdrawFromSlot(InventorySlot $slot, int $quantity): ?Product
    {
        $slot->removeStock($quantity);

        if ($slot->quantity()->isZero()) {
            $slot->disable();

            if ($slot->hasProductAssigned()) {
                $slot->clearProduct();
            }
        }

        return null;
    }

    private function resolveProductForProjection(InventorySlot $slot): ?Product
    {
        if (null === $slot->productId()) {
            return null;
        }

        return $this->productRepository->find($slot->productId());
    }

    private function syncProjection(string $machineId, string $slotCode, InventorySlot $slot, ?Product $product): void
    {
        /** @var SlotProjectionDocument|null $projection */
        $projection = $this->documentManager
            ->getRepository(SlotProjectionDocument::class)
            ->findOneBy([
                'machineId' => $machineId,
                'slotCode' => $slotCode,
            ]);

        if (null === $projection) {
            return;
        }

        $this->applyProjection($projection, $slot, $product);
        $this->documentManager->flush();
    }

    private function applyProjection(SlotProjectionDocument $projection, InventorySlot $slot, ?Product $product): void
    {
        $projection->syncFromInventory(
            slotQuantity: $slot->quantity()->value(),
            slotCapacity: $slot->capacity()->value(),
            status: $slot->status()->value,
            lowStock: $slot->needsRestock(),
            productId: $slot->productId()?->value(),
            productName: $product?->name()->value(),
            priceCents: $product?->price()->amountInCents(),
            recommendedSlotQuantity: $product?->recommendedSlotQuantity()->value(),
        );
    }
}
