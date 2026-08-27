<?php

namespace App\Support;

use App\Models\FinanceCategory;
use App\Models\School;

/**
 * Default cashbook categories auto-provisioned per school on first use of
 * the books module — the vocabulary an Ethiopian school bursar actually
 * works with. Schools rename/deactivate/extend their own list afterwards;
 * re-running never duplicates or resurrects.
 */
class FinanceCategories
{
    /** @return array<string, list<string>> kind => names */
    public static function defaults(): array
    {
        return [
            'expense' => [
                'Rent', 'Utilities', 'Teaching materials', 'Office supplies',
                'Maintenance & repairs', 'Transport & fuel', 'Food & catering',
                'Cleaning & sanitation', 'Security', 'Marketing',
                'Government fees & taxes', 'Bank charges', 'Other expenses',
            ],
            'income' => [
                'Hall & facility rental', 'Uniform sales', 'Book & stationery sales',
                'Canteen income', 'Donations & grants', 'Other income',
            ],
        ];
    }

    /** Provision the default list once per school (no-op afterwards). */
    public static function ensureSeeded(School $school): void
    {
        if (FinanceCategory::withTrashed()->where('school_id', $school->id)->exists()) {
            return;
        }

        foreach (self::defaults() as $kind => $names) {
            foreach ($names as $name) {
                FinanceCategory::create([
                    'school_id' => $school->id,
                    'kind' => $kind,
                    'name' => $name,
                    'is_active' => true,
                ]);
            }
        }
    }
}
