<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Domain\ValueObject;

enum VendingSessionState: string
{
    case Collecting = 'collecting';
    case Ready = 'ready';
    case Dispensing = 'dispensing';
    case Cancelled = 'cancelled';
    case Timeout = 'timeout';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Cancelled, self::Timeout, self::Dispensing => true,
            default => false,
        };
    }

    public function isOpen(): bool
    {
        return !$this->isTerminal();
    }
}
