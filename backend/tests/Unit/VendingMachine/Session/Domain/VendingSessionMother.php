<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Session\Domain;

use App\Tests\Unit\VendingMachine\Session\Domain\ValueObject\VendingSessionIdMother;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionId;
use App\VendingMachine\Session\Domain\VendingSession;

final class VendingSessionMother
{
    public static function start(?VendingSessionId $id = null): VendingSession
    {
        return VendingSession::start($id ?? VendingSessionIdMother::random());
    }
}
