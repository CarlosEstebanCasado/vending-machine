<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Application\Security;

use DateTimeImmutable;

final class AccessToken
{
    public function __construct(
        private readonly string $token,
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public function token(): string
    {
        return $this->token;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
