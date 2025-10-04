<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Application\Security;

use DateTimeImmutable;

final class TokenClaims
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        private readonly array $roles,
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function email(): string
    {
        return $this->email;
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return $this->roles;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
