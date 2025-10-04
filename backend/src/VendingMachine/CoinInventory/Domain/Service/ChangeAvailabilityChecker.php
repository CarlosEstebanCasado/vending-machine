<?php

declare(strict_types=1);

namespace App\VendingMachine\CoinInventory\Domain\Service;

use App\Shared\Money\Domain\Money;
use App\VendingMachine\CoinInventory\Domain\CoinInventory;
use App\VendingMachine\CoinInventory\Domain\ValueObject\CoinDenomination;
use DomainException;

final class ChangeAvailabilityChecker
{
    private const MAX_CHECK_AMOUNT = 95;
    private const STEP = 5;

    public function isChangeSufficient(CoinInventory $inventory): bool
    {
        $changeCapacity = $this->changeCapacity($inventory);

        if ($changeCapacity < self::STEP) {
            return false;
        }

        $maxAmountToCheck = min(self::MAX_CHECK_AMOUNT, $changeCapacity - ($changeCapacity % self::STEP));

        if (0 === $maxAmountToCheck) {
            return false;
        }

        for ($amount = self::STEP; $amount <= $maxAmountToCheck; $amount += self::STEP) {
            try {
                $inventory->planChangeFor(Money::fromCents($amount));
            } catch (DomainException) {
                return false;
            }
        }

        return true;
    }

    private function changeCapacity(CoinInventory $inventory): int
    {
        $available = $inventory->availableCoins()->toArray();
        $total = 0;

        foreach (CoinDenomination::changeDenominations() as $denomination) {
            $quantity = $available[$denomination->value] ?? 0;
            $total += $quantity * $denomination->value;
        }

        return $total;
    }
}
