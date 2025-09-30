<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Domain\ValueObject;

use App\VendingMachine\Session\Domain\ValueObject\VendingSessionId;

final class VendingSessionIdMother
{
    public static function random(): VendingSessionId
    {
        return VendingSessionId::fromString(uniqid('session-', true));
    }
}
