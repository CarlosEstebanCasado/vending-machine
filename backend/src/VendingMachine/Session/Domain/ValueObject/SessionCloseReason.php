<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Domain\ValueObject;

enum SessionCloseReason: string
{
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Timeout = 'timeout';
}
