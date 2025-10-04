<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\UI\Http\Controller;

use App\VendingMachine\CoinInventory\Application\GetInventory\CoinInventoryNotFound;
use App\VendingMachine\CoinInventory\Application\GetInventory\CoinInventoryResult;
use App\VendingMachine\CoinInventory\Application\GetInventory\GetCoinInventoryQuery;
use App\VendingMachine\CoinInventory\Application\GetInventory\GetCoinInventoryQueryHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route(path: '/coins', name: 'admin_coin_inventory', methods: ['GET'])]
final class GetCoinInventoryController
{
    public function __construct(
        private readonly GetCoinInventoryQueryHandler $handler,
        private readonly string $defaultMachineId,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $machineId = $request->query->get('machineId', $this->defaultMachineId);

        if (!is_string($machineId) || '' === $machineId) {
            return new JsonResponse(
                ['error' => ['message' => 'Parameter "machineId" is required.']],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        try {
            $result = $this->handler->handle(new GetCoinInventoryQuery($machineId));
        } catch (CoinInventoryNotFound $exception) {
            return new JsonResponse(
                ['error' => ['message' => $exception->getMessage()]],
                JsonResponse::HTTP_NOT_FOUND,
            );
        } catch (Throwable $exception) {
            return new JsonResponse(
                ['error' => ['message' => 'Unable to retrieve coin inventory.']],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse($this->serializeResult($result));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult(CoinInventoryResult $result): array
    {
        return [
            'machineId' => $result->machineId,
            'balances' => array_map(static fn (array $row) => [
                'denomination' => $row['denomination'],
                'available' => $row['available'],
                'reserved' => $row['reserved'],
            ], $result->balances),
            'insufficientChange' => $result->insufficientChange,
            'updatedAt' => $result->updatedAt,
        ];
    }
}
