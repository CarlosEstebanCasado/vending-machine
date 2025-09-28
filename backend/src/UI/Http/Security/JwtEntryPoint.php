<?php

namespace App\UI\Http\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationEntryPointInterface;

/**
 * Minimal JWT entry point returning a JSON 401 response until real auth is implemented.
 */
class JwtEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['message' => 'Authentication required'], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
