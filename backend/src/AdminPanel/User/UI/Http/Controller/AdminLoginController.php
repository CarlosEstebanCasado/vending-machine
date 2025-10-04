<?php

declare(strict_types=1);

namespace App\AdminPanel\User\UI\Http\Controller;

use App\AdminPanel\User\Application\Login\LoginAdminUserCommand;
use App\AdminPanel\User\Application\Login\LoginAdminUserCommandHandler;
use App\AdminPanel\User\Domain\Exception\AdminUserInactive;
use App\AdminPanel\User\Domain\Exception\AdminUserInvalidCredentials;
use App\AdminPanel\User\Domain\Exception\AdminUserNotFound;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/login', name: 'admin_login', methods: ['POST'])]
final class AdminLoginController
{
    public function __construct(private readonly LoginAdminUserCommandHandler $handler)
    {
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

        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        if ('' === $email || '' === $password) {
            return new JsonResponse(
                ['error' => ['message' => 'Email and password are required.']],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        try {
            $result = $this->handler->handle(new LoginAdminUserCommand($email, $password));
        } catch (AdminUserNotFound|AdminUserInvalidCredentials $exception) {
            return new JsonResponse(
                ['error' => ['message' => 'Invalid credentials.']],
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        } catch (AdminUserInactive $exception) {
            return new JsonResponse(
                ['error' => ['message' => 'Admin user inactive.']],
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        return new JsonResponse([
            'id' => $result->id,
            'email' => $result->email,
            'roles' => $result->roles,
            'token' => $result->token,
            'expiresAt' => $result->expiresAt,
        ]);
    }
}
