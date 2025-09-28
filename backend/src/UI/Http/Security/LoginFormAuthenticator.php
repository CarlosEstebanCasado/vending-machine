<?php

namespace App\UI\Http\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Stub form authenticator for future admin panel login handling.
 */
class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request)
    {
        throw new AuthenticationException('Login form not implemented yet.');
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('admin_login');
    }

    protected function getDefaultSuccessRedirectUrl(Request $request): string
    {
        return $this->urlGenerator->generate('admin_dashboard');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName)
    {
        return null;
    }
}
