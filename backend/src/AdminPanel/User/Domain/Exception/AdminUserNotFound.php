<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Domain\Exception;

use DomainException;

final class AdminUserNotFound extends DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('Admin user with email "%s" not found.', $email));
    }
}
