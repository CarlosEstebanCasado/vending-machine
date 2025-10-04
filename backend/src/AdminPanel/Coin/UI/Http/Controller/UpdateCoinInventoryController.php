<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\UI\Http\Controller;

use App\VendingMachine\CoinInventory\Application\AdjustInventory\AdjustCoinInventoryCommand;
use App\VendingMachine\CoinInventory\Application\AdjustInventory\AdjustCoinInventoryCommandHandler;
use App\VendingMachine\CoinInventory\Application\AdjustInventory\AdjustCoinInventoryOperation;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route(path: '/coins', name: 'admin_coin_inventory_update', methods: ['POST'])]
final class UpdateCoinInventoryController
{
    public function __construct(
        private readonly AdjustCoinInventoryCommandHandler $handler,
        private readonly string $defaultMachineId,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse(
                ['error' => ['message' => 'Invalid JSON payload.']],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        $machineId = (string) ($payload['machineId'] ?? $this->defaultMachineId);
        $operation = $payload['operation'] ?? null;
        $denominations = $payload['denominations'] ?? null;

        if ('' === $machineId) {
            return new JsonResponse(
                ['error' => ['message' => 'Machine id is required.']],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        if (!is_string($operation)) {
            return new JsonResponse(
                ['error' => ['message' => 'Operation must be provided.']],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        if (!is_array($denominations)) {
            return new JsonResponse(
                ['error' => ['message' => 'Denominations must be provided as an object.']],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        try {
            $command = new AdjustCoinInventoryCommand(
                machineId: $machineId,
                operation: AdjustCoinInventoryOperation::fromString($operation),
                denominations: $denominations,
            );

            ($this->handler)($command);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(
                ['error' => ['message' => $exception->getMessage()]],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        } catch (Throwable $exception) {
            return new JsonResponse(
                ['error' => ['message' => 'Unable to update coin inventory.']],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
