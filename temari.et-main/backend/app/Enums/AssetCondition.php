<?php

namespace App\Enums;

/**
 * Physical condition of an asset unit — updated on registration, on return
 * from a holder, and by hand when the storekeeper inspects.
 */
enum AssetCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Poor => 'Poor',
            self::Damaged => 'Damaged',
        };
    }
}
