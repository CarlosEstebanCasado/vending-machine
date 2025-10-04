<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\UI\Http\Controller;

use App\VendingMachine\Session\Application\VendProduct\VendProductCommand;
use App\VendingMachine\Session\Application\VendProduct\VendProductCommandHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class VendProductController extends AbstractController
{
    public function __construct(
        private readonly VendProductCommandHandler $handler,
        #[Autowire('%app.machine_id%')] private readonly string $machineId,
    ) {
    }

    #[Route('/machine/session/purchase', name: 'api_machine_session_purchase', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodeRequest($request);

        $result = $this->handler->handle(
            new VendProductCommand(
                machineId: $this->machineId,
                sessionId: $payload['session_id'],
            )
        );

        $session = $result->session;

        return new JsonResponse([
            'machine_id' => $this->machineId,
            'session' => [
                'id' => $session->sessionId,
                'state' => $session->state,
                'balance_cents' => $session->balanceCents,
                'inserted_coins' => $session->insertedCoins,
                'selected_product_id' => $session->selectedProductId,
                'selected_slot_code' => $session->selectedSlotCode,
            ],
            'sale' => [
                'status' => $result->status,
                'product_id' => $result->productId,
                'slot_code' => $result->slotCode,
                'price_cents' => $result->priceCents,
                'change_dispensed' => $result->changeDispensed,
                'returned_coins' => $result->returnedCoins,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * @return array{session_id: string}
     */
    private function decodeRequest(Request $request): array
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['session_id']) || !is_string($data['session_id'])) {
            throw new BadRequestHttpException('Missing or invalid "session_id".');
        }

        return [
            'session_id' => $data['session_id'],
        ];
    }
}
