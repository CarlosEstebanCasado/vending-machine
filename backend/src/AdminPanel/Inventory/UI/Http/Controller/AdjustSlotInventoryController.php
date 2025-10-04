<?php

declare(strict_types=1);

namespace App\AdminPanel\Inventory\UI\Http\Controller;

use App\VendingMachine\Inventory\Application\AdjustSlotInventory\AdjustSlotInventoryOperation;
use App\VendingMachine\Inventory\Application\AdjustSlotInventory\AdminAdjustSlotInventoryCommand;
use App\VendingMachine\Inventory\Application\AdjustSlotInventory\AdminAdjustSlotInventoryCommandHandler;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route(path: '/slots/stock', name: 'admin_inventory_adjust', methods: ['POST'])]
final class AdjustSlotInventoryController
{
    public function __construct(
        private readonly AdminAdjustSlotInventoryCommandHandler $handler,
        private readonly string $defaultMachineId,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse([
                'error' => ['message' => 'Invalid JSON payload.'],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $machineId = (string) ($payload['machineId'] ?? $this->defaultMachineId);
        $slotCode = $payload['slotCode'] ?? null;
        $operation = $payload['operation'] ?? null;
        $quantity = $payload['quantity'] ?? null;
        $productId = $payload['productId'] ?? null;

        if ('' === trim($machineId)) {
            return new JsonResponse([
                'error' => ['message' => 'Machine id is required.'],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!is_string($slotCode) || '' === trim($slotCode)) {
            return new JsonResponse([
                'error' => ['message' => 'Slot code is required.'],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!is_string($operation)) {
            return new JsonResponse([
                'error' => ['message' => 'Operation must be provided.'],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!is_int($quantity)) {
            return new JsonResponse([
                'error' => ['message' => 'Quantity must be provided as an integer.'],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $command = new AdminAdjustSlotInventoryCommand(
                machineId: $machineId,
                slotCode: $slotCode,
                operation: AdjustSlotInventoryOperation::fromString($operation),
                quantity: $quantity,
                productId: is_string($productId) ? $productId : null,
            );

            $this->handler->handle($command);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse([
                'error' => ['message' => $exception->getMessage()]], JsonResponse::HTTP_BAD_REQUEST);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'error' => ['message' => 'Unable to adjust slot inventory.']], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
