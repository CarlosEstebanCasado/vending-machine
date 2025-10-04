<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Application\Login;

use App\AdminPanel\User\Application\Security\AdminAccessTokenGenerator;
use App\AdminPanel\User\Domain\AdminUserRepository;
use App\AdminPanel\User\Domain\Exception\AdminUserInactive;
use App\AdminPanel\User\Domain\Exception\AdminUserInvalidCredentials;
use App\AdminPanel\User\Domain\Exception\AdminUserNotFound;
use DateTimeImmutable;

use function password_verify;

final class LoginAdminUserCommandHandler
{
    public function __construct(
        private readonly AdminUserRepository $repository,
        private readonly AdminAccessTokenGenerator $tokenGenerator,
    ) {
    }

    public function handle(LoginAdminUserCommand $command): LoginAdminUserResult
    {
        $adminUser = $this->repository->findByEmail(strtolower($command->email));

        if (null === $adminUser) {
            throw AdminUserNotFound::withEmail($command->email);
        }

        if (!$adminUser->isActive()) {
            throw AdminUserInactive::withEmail($adminUser->email());
        }

        if (!password_verify($command->password, $adminUser->passwordHash())) {
            throw AdminUserInvalidCredentials::withEmail($adminUser->email());
        }

        $accessToken = $this->tokenGenerator->generate($adminUser);

        return new LoginAdminUserResult(
            id: $adminUser->id(),
            email: $adminUser->email(),
            roles: $adminUser->roles(),
            token: $accessToken->token(),
            expiresAt: $accessToken->expiresAt()->format(DateTimeImmutable::ATOM),
        );
    }
}
