<?php

declare(strict_types=1);

namespace App\VendingMachine\Machine\UI\Http\Controller;

use App\VendingMachine\Machine\Application\GetMachineState\GetMachineStateQueryHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class MachineStateController extends AbstractController
{
    public function __construct(private readonly GetMachineStateQueryHandler $handler)
    {
    }

    #[Route('/machine/state', name: 'api_machine_state', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $view = ($this->handler)();

        return new JsonResponse($view->toArray());
    }
}
