<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\UI\Http\Controller;

use App\VendingMachine\Session\Application\SelectProduct\SelectProductCommand;
use App\VendingMachine\Session\Application\SelectProduct\SelectProductCommandHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class SelectProductController extends AbstractController
{
    public function __construct(
        private readonly SelectProductCommandHandler $handler,
        #[Autowire('%app.machine_id%')] private readonly string $machineId,
    ) {
    }

    #[Route('/machine/session/product', name: 'api_machine_session_select_product', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodeRequest($request);

        $result = $this->handler->handle(
            new SelectProductCommand(
                machineId: $this->machineId,
                sessionId: $payload['session_id'],
                productId: $payload['product_id'],
                slotCode: $payload['slot_code'],
            )
        );

        return new JsonResponse([
            'machine_id' => $this->machineId,
            'session' => [
                'id' => $result->sessionId,
                'state' => $result->state,
                'balance_cents' => $result->balanceCents,
                'inserted_coins' => $result->insertedCoins,
                'selected_product_id' => $result->selectedProductId,
                'selected_slot_code' => $result->selectedSlotCode,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * @return array{session_id: string, product_id: string, slot_code: string}
     */
    private function decodeRequest(Request $request): array
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['session_id']) || !is_string($data['session_id'])) {
            throw new BadRequestHttpException('Missing or invalid "session_id".');
        }

        if (!isset($data['product_id']) || !is_string($data['product_id'])) {
            throw new BadRequestHttpException('Missing or invalid "product_id".');
        }

        if (!isset($data['slot_code']) || !is_string($data['slot_code'])) {
            throw new BadRequestHttpException('Missing or invalid "slot_code".');
        }

        return [
            'session_id' => $data['session_id'],
            'product_id' => $data['product_id'],
            'slot_code' => $data['slot_code'],
        ];
    }
}
