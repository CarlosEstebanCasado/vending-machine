<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Domain\Exception;

use DomainException;

final class AdminUserInvalidCredentials extends DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('Invalid credentials for admin user "%s".', $email));
    }
}
