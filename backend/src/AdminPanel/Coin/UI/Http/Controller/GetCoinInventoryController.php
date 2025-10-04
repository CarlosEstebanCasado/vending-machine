<?php

declare(strict_types=1);

namespace App\AdminPanel\Coin\UI\Http\Controller;

use App\AdminPanel\Coin\Application\GetCoinInventory\AdminCoinInventoryResult;
use App\AdminPanel\Coin\Application\GetCoinInventory\AdminGetCoinInventoryQuery;
use App\AdminPanel\Coin\Application\GetCoinInventory\AdminGetCoinInventoryQueryHandler;
use App\AdminPanel\Coin\Application\GetCoinInventory\CoinInventoryNotFound;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route(path: '/coins', name: 'admin_coin_inventory', methods: ['GET'])]
final class GetCoinInventoryController
{
    public function __construct(
        private readonly AdminGetCoinInventoryQueryHandler $handler,
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
            $result = ($this->handler)(new AdminGetCoinInventoryQuery($machineId));
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
    private function serializeResult(AdminCoinInventoryResult $result): array
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
