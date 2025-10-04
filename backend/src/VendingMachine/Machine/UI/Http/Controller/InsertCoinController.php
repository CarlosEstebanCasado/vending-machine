<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\UI\Http\Controller;

use App\VendingMachine\Session\Application\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Session\Application\InsertCoin\InsertCoinCommandHandler;
use App\VendingMachine\Session\Application\StartSession\StartSessionResult;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class InsertCoinController extends AbstractController
{
    public function __construct(
        private readonly InsertCoinCommandHandler $handler,
        #[Autowire('%app.machine_id%')] private readonly string $machineId,
    ) {
    }

    #[Route('/machine/session/coin', name: 'api_machine_session_insert_coin', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodeRequest($request);
        $result = $this->handler->handle($this->createCommand($payload));

        return new JsonResponse($this->sessionResponse($result), Response::HTTP_OK);
    }

    /**
     * @return array{session_id: string, denomination_cents: int}
     */
    private function decodeRequest(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Invalid JSON payload.');
        }

        if (!isset($data['session_id']) || !is_string($data['session_id'])) {
            throw new BadRequestHttpException('Missing or invalid "session_id".');
        }

        if (!isset($data['denomination_cents']) || !is_int($data['denomination_cents'])) {
            throw new BadRequestHttpException('Missing or invalid "denomination_cents".');
        }

        return [
            'session_id' => $data['session_id'],
            'denomination_cents' => $data['denomination_cents'],
        ];
    }

    /**
     * @param array{session_id: string, denomination_cents: int} $payload
     */
    private function createCommand(array $payload): InsertCoinCommand
    {
        return new InsertCoinCommand(
            machineId: $this->machineId,
            sessionId: $payload['session_id'],
            denomination: $payload['denomination_cents'],
        );
    }

    private function sessionResponse(StartSessionResult $result): array
    {
        return [
            'machine_id' => $this->machineId,
            'session' => [
                'id' => $result->sessionId,
                'state' => $result->state,
                'balance_cents' => $result->balanceCents,
                'inserted_coins' => $result->insertedCoins,
                'selected_product_id' => $result->selectedProductId,
                'selected_slot_code' => $result->selectedSlotCode,
            ],
        ];
    }
}
