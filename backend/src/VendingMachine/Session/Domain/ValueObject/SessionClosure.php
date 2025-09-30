<?php

declare(strict_types=1);

namespace App\VendingMachine\Session\Domain\ValueObject;

use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinBundle;

final class SessionClosure
{
    public function __construct(
        private readonly CoinBundle $insertedCoins,
        private readonly ?CoinBundle $changePlan
    ) {
    }

    public function insertedCoins(): CoinBundle
    {
        return $this->insertedCoins;
    }

    public function changePlan(): ?CoinBundle
    {
        return $this->changePlan;
    }
}
