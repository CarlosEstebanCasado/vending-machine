<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Application\Security;

interface AdminAccessTokenValidator
{
    /**
     * @throws \App\AdminPanel\User\Infrastructure\Token\InvalidAdminToken
     */
    public function validate(string $token): TokenClaims;
}
