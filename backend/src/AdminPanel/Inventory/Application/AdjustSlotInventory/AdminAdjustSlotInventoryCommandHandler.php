<?php

declare(strict_types=1);

namespace App\AdminPanel\Inventory\Application\AdjustSlotInventory;

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

    public function __invoke(AdminAdjustSlotInventoryCommand $command): void
    {
        if ($command->quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $slot = $this->slotRepository->findByMachineAndCode($command->machineId, SlotCode::fromString($command->slotCode));

        if (null === $slot) {
            throw new InvalidArgumentException(sprintf('Slot "%s" not found for machine "%s".', $command->slotCode, $command->machineId));
        }

        if (AdjustSlotInventoryOperation::Restock === $command->operation && $slot->status()->isReserved()) {
            throw new InvalidArgumentException('Slot cannot be restocked while it is reserved by an active session.');
        }

        $product = null;

        if (AdjustSlotInventoryOperation::Restock === $command->operation) {
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
        } else {
            $slot->removeStock($command->quantity);

            if ($slot->quantity()->isZero()) {
                $slot->disable();
                if ($slot->hasProductAssigned()) {
                    $slot->clearProduct();
                }
            }
        }

        if (null === $product && null !== $slot->productId()) {
            $product = $this->productRepository->find($slot->productId());
        }

        $this->slotRepository->save($slot, $command->machineId);

        /** @var SlotProjectionDocument|null $projection */
        $projection = $this->documentManager->getRepository(SlotProjectionDocument::class)->findOneBy([
            'machineId' => $command->machineId,
            'slotCode' => $command->slotCode,
        ]);

        if (null !== $projection) {
            $this->applyProjection($projection, $slot, $product);
            $this->documentManager->flush();
        }
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
