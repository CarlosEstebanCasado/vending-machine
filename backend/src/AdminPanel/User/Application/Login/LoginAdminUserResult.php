<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Application\Login;

final readonly class LoginAdminUserResult
{
    public function __construct(
        public string $id,
        public string $email,
        public array $roles,
        public string $token,
        public string $expiresAt,
    ) {
    }
}
