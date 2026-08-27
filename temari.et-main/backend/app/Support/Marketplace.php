<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * Tutoring-marketplace policy knobs, all operator-editable platform
 * settings with safe defaults: the commission Temari.et keeps from released
 * cycles (a per-tutor override may still win), boost pricing, and the
 * escrow release mode (manual until Abdul flips auto-release on).
 */
class Marketplace
{
    public const SETTING_KEY = 'marketplace.settings';

    public const DEFAULT_COMMISSION_PERCENT = 10.0;

    /** Sessions unconfirmed this many hours after logging auto-confirm. */
    public const AUTO_CONFIRM_HOURS = 72;

    /**
     * @return array{commission_percent: float, boost_weekly_price: float, boost_monthly_price: float, auto_release_days: ?int}
     */
    public static function settings(): array
    {
        $stored = (array) (PlatformSetting::get(self::SETTING_KEY) ?? []);

        return [
            'commission_percent' => (float) ($stored['commission_percent'] ?? self::DEFAULT_COMMISSION_PERCENT),
            'boost_weekly_price' => (float) ($stored['boost_weekly_price'] ?? 150.0),
            'boost_monthly_price' => (float) ($stored['boost_monthly_price'] ?? 500.0),
            // null = every release is approved by Temari.et staff (launch
            // mode); N = auto-release N days after the cycle month ends.
            'auto_release_days' => isset($stored['auto_release_days']) && $stored['auto_release_days'] !== null
                ? (int) $stored['auto_release_days']
                : null,
        ];
    }

    public static function commissionPercent(): float
    {
        return self::settings()['commission_percent'];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function update(array $values): void
    {
        PlatformSetting::set(self::SETTING_KEY, array_merge(self::settings(), $values));
    }
}
