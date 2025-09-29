<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Infrastructure\User\Document;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Temporary MongoDB document stub for admin accounts.
 * Replace with real fields and ODM mapping.
 */
class AdminUserDocument implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_ADMIN'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getUserIdentifier(): string
    {
        return 'placeholder@example.com';
    }
}
