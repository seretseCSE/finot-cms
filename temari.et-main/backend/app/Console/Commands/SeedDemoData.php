<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * One-shot demo world for manual testing on local/staging: 10 schools with
 * 1–6 branches each, staff in every role, 1–4 years of academic history,
 * students + guardians, payment accounts + fees with collection accounts,
 * tuition invoices in every state (incl. overdue) with account-snapshotted
 * payments, fee concessions (policy suggestions + manual grants), continuous
 * assessments and timetables — all Ethiopian sample data. In production the
 * command refuses to run unless the caller supplies the ADMIN_PASSWORD
 * configured in .env.
 */
class SeedDemoData extends Command
{
    protected $signature = 'temari:seed-demo
        {--fresh : Reset the database first (migrate:fresh --seed), then build the demo world}
        {--admin-password= : Required in production — must match ADMIN_PASSWORD in .env (prompted if omitted)}';

    protected $description = 'Seed realistic Ethiopian demo data: 10 schools, branches, staff, students, billing (accounts, invoices, payments, concessions), academic history, timetables';

    public function handle(): int
    {
        if (! $this->authorizeRun()) {
            return self::FAILURE;
        }

        DemoSeeder::$authorized = true;

        if ($this->option('fresh')) {
            $this->components->info('Resetting database — migrate:fresh --seed');
            $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }

    /**
     * Local/staging run freely. Production is locked behind ADMIN_PASSWORD:
     * no password configured means no demo seeding at all, and a wrong
     * password aborts before anything touches the database.
     */
    private function authorizeRun(): bool
    {
        if (! app()->isProduction()) {
            return true;
        }

        $expected = config('app.admin_password');

        if (! is_string($expected) || $expected === '') {
            $this->components->error('Demo seeding is disabled in production: ADMIN_PASSWORD is not set in .env.');

            return false;
        }

        $given = $this->option('admin-password') ?? $this->secret('Admin password');

        if (! is_string($given) || ! hash_equals($expected, $given)) {
            $this->components->error('Wrong admin password — nothing was seeded.');

            return false;
        }

        return $this->confirm(
            'You are about to write DEMO data into the PRODUCTION database'
            .($this->option('fresh') ? ' — and --fresh will WIPE it first' : '')
            .'. Continue?',
        );
    }
}
