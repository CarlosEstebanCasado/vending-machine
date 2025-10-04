<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Infrastructure\Security;

use App\AdminPanel\User\Domain\AdminUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class AdminUserIdentity implements UserInterface
{
    public function __construct(private readonly AdminUser $adminUser)
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->adminUser->id();
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = $this->adminUser->roles();

        return array_values(array_unique(array_map(
            static fn (string $role): string => str_starts_with($role, 'ROLE_') ? $role : sprintf('ROLE_%s', strtoupper($role)),
            $roles,
        )));
    }

    public function eraseCredentials(): void
    {
    }

    public function getAdminUser(): AdminUser
    {
        return $this->adminUser;
    }
}
