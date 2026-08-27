<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * DemoSeeder used to seed every demo account with the password `password`,
 * which the digits-only PIN standard on the login/signup/reset forms can
 * never accept — so no seeded account could sign in through the UI. The
 * seeder now uses the numeric PIN `123456`; this migration brings the rows
 * that are already in the database (staging carries seeded demo data) in
 * line.
 *
 * Cheap detection: the seeder hashed the password ONCE per run and stamped
 * the SAME bcrypt hash on every account it created. Real accounts can never
 * share a hash (unique salts), so any hash shared by many users is a seeder
 * hash — verify it really is `password`, then re-point those rows at the
 * new PIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sharedHashes = DB::table('users')
            ->select('password')
            ->whereNotNull('password')
            ->groupBy('password')
            ->havingRaw('count(*) > 10')
            ->pluck('password');

        $newHash = null;
        foreach ($sharedHashes as $hash) {
            if (! Hash::check('password', $hash)) {
                continue;
            }
            $newHash ??= Hash::make('123456');
            DB::table('users')->where('password', $hash)->update(['password' => $newHash]);
        }
    }

    public function down(): void
    {
        // Restoring an unusable demo password would just re-break demo
        // logins. Intentionally a no-op.
    }
};
