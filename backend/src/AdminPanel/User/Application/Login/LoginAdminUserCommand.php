<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Application\Login;

final readonly class LoginAdminUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
