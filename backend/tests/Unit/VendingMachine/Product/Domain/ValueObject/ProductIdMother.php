<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Product\Domain\ValueObject;

use App\VendingMachine\Product\Domain\ValueObject\ProductId;

final class ProductIdMother
{
    public static function random(): ProductId
    {
        return ProductId::fromString(self::uuid());
    }

    private static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }
}
