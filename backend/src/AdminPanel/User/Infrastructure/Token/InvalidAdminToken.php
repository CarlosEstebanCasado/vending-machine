<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Infrastructure\Token;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class InvalidAdminToken extends AuthenticationException
{
    public static function emptyToken(): self
    {
        return new self('Empty token.');
    }

    public static function malformed(): self
    {
        return new self('Malformed token.');
    }

    public static function unsupportedAlgorithm(): self
    {
        return new self('Unsupported token algorithm.');
    }

    public static function signatureMismatch(): self
    {
        return new self('Token signature mismatch.');
    }

    public static function missingClaims(): self
    {
        return new self('Token is missing required claims.');
    }

    public static function expired(): self
    {
        return new self('Token has expired.');
    }
}
