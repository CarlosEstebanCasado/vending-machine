<?php

declare(strict_types=1);

namespace App\AdminPanel\Inventory\UI\Http\Controller;

use App\VendingMachine\Inventory\Application\GetSlots\AdminGetSlotsQuery;
use App\VendingMachine\Inventory\Application\GetSlots\AdminGetSlotsQueryHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/slots', name: 'admin_inventory_slots', methods: ['GET'])]
final class GetSlotInventoryController
{
    public function __construct(
        private readonly AdminGetSlotsQueryHandler $handler,
        private readonly string $defaultMachineId,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $machineId = $request->query->get('machineId', $this->defaultMachineId);

        if (!is_string($machineId) || '' === trim($machineId)) {
            return new JsonResponse([
                'error' => ['message' => 'Parameter "machineId" is required.'],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $this->handler->handle(new AdminGetSlotsQuery($machineId));

        return new JsonResponse($this->serializeResult($result));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult($result): array
    {
        return [
            'machineId' => $result->machineId,
            'slots' => array_map(static function ($slot) {
                return [
                    'code' => $slot->slotCode,
                    'status' => $slot->status,
                    'capacity' => $slot->capacity,
                    'quantity' => $slot->quantity,
                    'restockThreshold' => $slot->restockThreshold,
                    'needsRestock' => $slot->needsRestock,
                    'productId' => $slot->productId,
                    'productName' => $slot->productName,
                    'priceCents' => $slot->priceCents,
                    'recommendedSlotQuantity' => $slot->recommendedSlotQuantity,
                ];
            }, $result->slots),
        ];
    }
}
