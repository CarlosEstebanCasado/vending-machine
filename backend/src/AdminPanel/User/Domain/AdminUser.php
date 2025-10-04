<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Domain;

final class AdminUser
{
    public function __construct(
        private readonly string $id,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly array $roles,
        private readonly bool $active,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
