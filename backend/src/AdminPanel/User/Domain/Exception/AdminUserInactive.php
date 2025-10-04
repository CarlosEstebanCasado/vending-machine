<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Domain\Exception;

use DomainException;

final class AdminUserInactive extends DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('Admin user "%s" is inactive.', $email));
    }
}
