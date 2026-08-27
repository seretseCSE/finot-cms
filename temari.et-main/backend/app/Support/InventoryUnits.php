<?php

namespace App\Support;

/**
 * Units of measure for inventory items. A plain validated list (not an
 * enum): stable slugs on the wire, labels translated on the frontend.
 * Mirrors the frontend list in lib/i18n inventory.units — keep in sync.
 */
class InventoryUnits
{
    public const ALL = [
        'piece', 'set', 'pair', 'box', 'pack', 'ream', 'dozen', 'roll',
        'bottle', 'carton', 'kg', 'g', 'litre', 'ml', 'meter',
    ];
}
