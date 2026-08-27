<?php

namespace App\Support;

use App\Enums\GatewayPurpose;
use App\Models\PlatformSetting;

/**
 * The gateway registry + the operator's enable/purpose matrix. Which gateways
 * exist (and how to talk to them) is code; whether each is ON and which
 * purposes it serves is a platform setting Temari.et staff edit — the same
 * split as the SMS whitelist. Credentials live in env (config/services.php)
 * only; a gateway with no credentials reports `configured: false` and is
 * never offered at checkout even when enabled.
 */
class PaymentGateways
{
    public const SETTING_KEY = 'payments.gateways';

    public const CODES = ['chapa', 'telebirr', 'cbebirr', 'fake'];

    /** Enabled-by-default matrix used until the operator first saves one. */
    private const DEFAULTS = [
        'chapa' => ['enabled' => true, 'purposes' => ['tutoring_cycle', 'ai_subscription', 'profile_boost', 'school_plan']],
        'telebirr' => ['enabled' => false, 'purposes' => ['tutoring_cycle', 'ai_subscription', 'profile_boost']],
        'cbebirr' => ['enabled' => false, 'purposes' => []],
        // The simulator: local/staging demos + tests. Hard-blocked in
        // production by configured() regardless of the setting.
        'fake' => ['enabled' => false, 'purposes' => ['tutoring_cycle', 'ai_subscription', 'profile_boost', 'school_plan']],
    ];

    public static function label(string $code): string
    {
        return match ($code) {
            'chapa' => 'Chapa',
            'telebirr' => 'Telebirr',
            'cbebirr' => 'CBE Birr',
            'fake' => 'Simulator',
            default => $code,
        };
    }

    /**
     * The operator matrix merged over defaults.
     *
     * @return array<string, array{enabled: bool, purposes: list<string>}>
     */
    public static function matrix(): array
    {
        $stored = PlatformSetting::get(self::SETTING_KEY) ?? [];
        $matrix = [];

        foreach (self::CODES as $code) {
            $row = array_merge(self::DEFAULTS[$code], $stored[$code] ?? []);
            $matrix[$code] = [
                'enabled' => (bool) $row['enabled'],
                'purposes' => array_values(array_intersect(
                    array_map(fn (GatewayPurpose $p) => $p->value, GatewayPurpose::cases()),
                    (array) $row['purposes'],
                )),
            ];
        }

        return $matrix;
    }

    /** Credentials present for this gateway? (Env-driven, never in DB.) */
    public static function configured(string $code): bool
    {
        return match ($code) {
            'chapa' => filled(config('services.chapa.secret_key')),
            'telebirr' => filled(config('services.telebirr.fabric_app_id'))
                && filled(config('services.telebirr.app_secret'))
                && filled(config('services.telebirr.merchant_app_id'))
                && filled(config('services.telebirr.private_key')),
            'cbebirr' => filled(config('services.cbebirr.api_key')),
            'fake' => ! app()->isProduction(),
            default => false,
        };
    }

    /**
     * Gateways a payer may choose for $purpose right now: enabled by the
     * operator, credentials present, purpose ticked.
     *
     * @return list<string>
     */
    public static function availableFor(GatewayPurpose $purpose): array
    {
        $codes = [];

        foreach (self::matrix() as $code => $row) {
            if ($row['enabled'] && self::configured($code) && in_array($purpose->value, $row['purposes'], true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
