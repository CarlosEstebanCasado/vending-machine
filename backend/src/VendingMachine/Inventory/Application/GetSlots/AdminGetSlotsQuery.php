<?php

declare(strict_types=1);

namespace App\VendingMachine\Inventory\Application\GetSlots;

final readonly class AdminGetSlotsQuery
{
    public function __construct(public string $machineId)
    {
    }
}
