<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\UI\Http\Controller;

use App\VendingMachine\Session\Application\ReturnCoins\ReturnCoinsCommand;
use App\VendingMachine\Session\Application\ReturnCoins\ReturnCoinsCommandHandler;
use App\VendingMachine\Session\Application\ReturnCoins\ReturnCoinsResult;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class ReturnCoinsController extends AbstractController
{
    public function __construct(
        private readonly ReturnCoinsCommandHandler $handler,
        #[Autowire('%app.machine_id%')] private readonly string $machineId,
    ) {
    }

    #[Route('/machine/session/coins/return', name: 'api_machine_session_return_coins', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodeRequest($request);
        $result = $this->handler->handle($this->createCommand($payload));

        return new JsonResponse($this->responsePayload($result), Response::HTTP_OK);
    }

    /**
     * @return array{session_id: string}
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

        return [
            'session_id' => $data['session_id'],
        ];
    }

    /**
     * @param array{session_id: string} $payload
     */
    private function createCommand(array $payload): ReturnCoinsCommand
    {
        return new ReturnCoinsCommand(
            machineId: $this->machineId,
            sessionId: $payload['session_id'],
        );
    }

    private function responsePayload(ReturnCoinsResult $result): array
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
            'returned_coins' => $result->returnedCoins,
        ];
    }
}
