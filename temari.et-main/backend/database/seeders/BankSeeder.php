<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Platform catalog of Ethiopian banks + mobile wallets (NBE-licensed, July
 * 2026). Codes are stable machine keys; where a logo exists under
 * public/images/banks it is referenced, otherwise the UI falls back to
 * initials. Idempotent — safe to re-run.
 */
class BankSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name, type, has_logo]
        $banks = [
            // Banks
            ['cbe',      'Commercial Bank of Ethiopia',   'bank', true],
            ['awash',    'Awash Bank',                    'bank', true],
            ['boa',      'Bank of Abyssinia',             'bank', true],
            ['dashen',   'Dashen Bank',                   'bank', true],
            ['amhara',   'Amhara Bank',                   'bank', true],
            ['siinqee',  'Siinqee Bank',                  'bank', true],
            ['zemen',    'Zemen Bank',                    'bank', true],
            ['hibret',   'Hibret Bank',                   'bank', false],
            ['wegagen',  'Wegagen Bank',                  'bank', false],
            ['nib',      'Nib International Bank',        'bank', false],
            ['oromia',   'Oromia Bank',                   'bank', false],
            ['coop',     'Cooperative Bank of Oromia',    'bank', false],
            ['abay',     'Abay Bank',                     'bank', false],
            ['berhan',   'Berhan Bank',                   'bank', false],
            ['bunna',    'Bunna Bank',                    'bank', false],
            ['enat',     'Enat Bank',                     'bank', false],
            ['global',   'Global Bank Ethiopia',          'bank', false],
            ['addis',    'Addis International Bank',      'bank', false],
            ['lion',     'Lion International Bank',       'bank', false],
            ['tsedey',   'Tsedey Bank',                   'bank', false],
            ['tsehay',   'Tsehay Bank',                   'bank', false],
            ['hijra',    'Hijra Bank',                    'bank', false],
            ['zamzam',   'ZamZam Bank',                   'bank', false],
            ['shabelle', 'Shabelle Bank',                 'bank', false],
            ['sidama',   'Sidama Bank',                   'bank', false],
            ['omo',      'Omo Bank',                      'bank', false],
            ['gadaa',    'Gadaa Bank',                    'bank', false],
            ['ahadu',    'Ahadu Bank',                    'bank', false],
            ['goh',      'Goh Betoch Bank',               'bank', false],
            ['rammis',   'Rammis Bank',                   'bank', false],
            ['dbe',      'Development Bank of Ethiopia',  'bank', false],

            // Mobile wallets
            ['telebirr', 'Telebirr',                      'wallet', true],
            ['cbebirr',  'CBE Birr',                      'wallet', true],
            ['mpesa',    'M-PESA',                        'wallet', true],
        ];

        $now = now();
        $rows = array_map(fn (array $b): array => [
            'code' => $b[0],
            'name' => $b[1],
            'type' => $b[2],
            'logo' => $b[3] ? "/images/banks/{$b[0]}.svg" : null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $banks);

        DB::table('banks')->upsert($rows, ['code'], ['name', 'type', 'logo', 'is_active', 'updated_at']);
    }
}
