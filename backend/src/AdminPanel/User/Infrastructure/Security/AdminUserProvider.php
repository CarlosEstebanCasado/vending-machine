<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Infrastructure\Security;

use App\AdminPanel\User\Domain\AdminUserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AdminUserProvider implements UserProviderInterface
{
    public function __construct(private readonly AdminUserRepository $repository)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $adminUser = $this->repository->findById($identifier);

        if (null === $adminUser) {
            throw new UserNotFoundException(sprintf('Admin user "%s" not found.', $identifier));
        }

        return new AdminUserIdentity($adminUser);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AdminUserIdentity) {
            throw new UnsupportedUserException('Unsupported user type.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return AdminUserIdentity::class === $class;
    }
}
