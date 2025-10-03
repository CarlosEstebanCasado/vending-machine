<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\UI\Http\Controller;

use App\VendingMachine\Session\Application\StartSession\StartSessionCommand;
use App\VendingMachine\Session\Application\StartSession\StartSessionCommandHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class StartMachineSessionController extends AbstractController
{
    public function __construct(
        private readonly StartSessionCommandHandler $handler,
        #[Autowire('%app.machine_id%')] private readonly string $machineId,
    ) {
    }

    #[Route('/machine/session', name: 'api_machine_session_start', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        $result = $this->handler->handle(new StartSessionCommand($this->machineId));

        return new JsonResponse([
            'machine_id' => $this->machineId,
            'session' => [
                'id' => $result->sessionId,
                'state' => $result->state,
                'balance_cents' => $result->balanceCents,
                'inserted_coins' => $result->insertedCoins,
                'selected_product_id' => $result->selectedProductId,
            ],
        ], Response::HTTP_CREATED);
    }
}
