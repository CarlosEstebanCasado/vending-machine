<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\VendingMachine\Session\Application\StartSession\StartSessionCommand;
use App\VendingMachine\Session\Application\StartSession\StartSessionResult;
use App\VendingMachine\Session\Domain\ValueObject\VendingSessionState;

final class StartSessionCommandHandlerStub
{
    public function handle(StartSessionCommand $command): StartSessionResult
    {
        return new StartSessionResult(
            sessionId: 'stub-session-id',
            state: VendingSessionState::Collecting->value,
            balanceCents: 0,
            insertedCoins: [],
            selectedProductId: null,
        );
    }
}
