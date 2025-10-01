<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Transaction\Domain\ValueObject;

use App\VendingMachine\Transaction\Domain\ValueObject\AdminUserId;

final class AdminUserIdMother
{
    public static function random(): AdminUserId
    {
        return AdminUserId::fromString(uniqid('admin-', true));
    }
}
