<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Infrastructure\Mongo;

use App\VendingMachine\Inventory\Domain\InventorySlot;
use App\VendingMachine\Inventory\Domain\InventorySlotRepository;
use App\VendingMachine\Inventory\Domain\ValueObject\InventorySlotId;
use App\VendingMachine\Inventory\Domain\ValueObject\RestockThreshold;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCapacity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotCode;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotQuantity;
use App\VendingMachine\Inventory\Domain\ValueObject\SlotStatus;
use App\VendingMachine\Inventory\Infrastructure\Mongo\Document\InventorySlotDocument;
use App\VendingMachine\Product\Domain\ValueObject\ProductId;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

final class MongoInventorySlotRepository implements InventorySlotRepository
{
    public function __construct(private readonly DocumentManager $documentManager)
    {
    }

    public function find(InventorySlotId $id): ?InventorySlot
    {
        /** @var InventorySlotDocument|null $document */
        $document = $this->documentManager->find(InventorySlotDocument::class, $id->value());

        if (null === $document) {
            return null;
        }

        return $this->toDomain($document);
    }

    public function findByMachineAndCode(string $machineId, SlotCode $code): ?InventorySlot
    {
        /** @var InventorySlotDocument|null $document */
        $document = $this->repository()->findOneBy([
            'machineId' => $machineId,
            'code' => $code->value(),
        ]);

        if (null === $document) {
            return null;
        }

        return $this->toDomain($document);
    }

    public function findByMachine(string $machineId): array
    {
        /** @var InventorySlotDocument[] $documents */
        $documents = $this->repository()->findBy(['machineId' => $machineId]);

        return array_map(fn (InventorySlotDocument $document): InventorySlot => $this->toDomain($document), $documents);
    }

    public function save(InventorySlot $slot, string $machineId): void
    {
        $documentId = $slot->id()->value();
        /** @var InventorySlotDocument|null $document */
        $document = $this->documentManager->find(InventorySlotDocument::class, $documentId);

        if (null === $document) {
            $document = new InventorySlotDocument(
                machineId: $machineId,
                code: $slot->code()->value(),
                capacity: $slot->capacity()->value(),
                quantity: $slot->quantity()->value(),
                restockThreshold: $slot->restockThreshold()->value(),
                status: $slot->status()->value,
                productId: $slot->productId()?->value(),
                id: $documentId,
            );
            $this->documentManager->persist($document);
        } else {
            $document->update(
                $slot->capacity()->value(),
                $slot->quantity()->value(),
                $slot->restockThreshold()->value(),
                $slot->status()->value,
                $slot->productId()?->value(),
            );
        }

        $this->documentManager->flush();
    }

    private function toDomain(InventorySlotDocument $document): InventorySlot
    {
        return InventorySlot::restore(
            InventorySlotId::fromString($document->id()),
            SlotCode::fromString($document->code()),
            SlotCapacity::fromInt($document->capacity()),
            SlotQuantity::fromInt($document->quantity()),
            RestockThreshold::fromInt($document->restockThreshold()),
            SlotStatus::from($document->status()),
            null !== $document->productId() ? ProductId::fromString($document->productId()) : null,
        );
    }

    private function repository(): DocumentRepository
    {
        /** @var DocumentRepository $repository */
        $repository = $this->documentManager->getRepository(InventorySlotDocument::class);

        return $repository;
    }
}
