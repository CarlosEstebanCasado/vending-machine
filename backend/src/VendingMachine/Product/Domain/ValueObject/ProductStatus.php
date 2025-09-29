<?php

declare(strict_types=1);

namespace App\VendingMachine\Product\Domain\ValueObject;

enum ProductStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function isActive(): bool
    {
        return self::Active === $this;
    }
}
