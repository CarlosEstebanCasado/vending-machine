<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Domain\ValueObject;

enum SlotStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Disabled = 'disabled';

    public function isAvailable(): bool
    {
        return self::Available === $this;
    }

    public function isReserved(): bool
    {
        return self::Reserved === $this;
    }

    public function isDisabled(): bool
    {
        return self::Disabled === $this;
    }
}
