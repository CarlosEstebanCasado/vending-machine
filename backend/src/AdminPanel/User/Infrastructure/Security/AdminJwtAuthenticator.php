<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Infrastructure\Security;

use App\AdminPanel\User\Application\Security\AdminAccessTokenValidator;
use App\AdminPanel\User\Infrastructure\Token\InvalidAdminToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Throwable;

final class AdminJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly AdminAccessTokenValidator $tokenValidator)
    {
    }

    public function supports(Request $request): ?bool
    {
        $header = $request->headers->get('Authorization');

        return null !== $header && str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $header = $request->headers->get('Authorization', '');
        $token = trim(substr($header, 7));

        try {
            $claims = $this->tokenValidator->validate($token);
        } catch (InvalidAdminToken $exception) {
            throw $exception;
        } catch (Throwable) {
            throw InvalidAdminToken::malformed();
        }

        $request->attributes->set('admin_token_claims', $claims);

        return new SelfValidatingPassport(new UserBadge(
            userIdentifier: $claims->userId(),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
