<?php

namespace Database\Seeders;

use App\Models\SystemBackup;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemBackupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superadmin = User::whereHas('roles', function ($query) {
            $query->where('name', 'Superadmin');
        })->first();

        if (!$superadmin) {
            return;
        }

        // Sample backup records
        $backups = [
            [
                'filename' => 'backup_2026-02-27_10-30-00_abc12345.zip',
                'path' => 'backups/',
                'size' => 52428800, // 50 MB
                'status' => 'completed',
                'log_message' => 'Backup completed successfully. Database and files backed up.',
                'created_by' => $superadmin->id,
                'completed_at' => now()->subHours(2),
            ],
            [
                'filename' => 'backup_2026-02-27_08-15-30_def67890.zip',
                'path' => 'backups/',
                'size' => 47185920, // 45 MB
                'status' => 'completed',
                'log_message' => 'Backup completed successfully. Database and files backed up.',
                'created_by' => $superadmin->id,
                'completed_at' => now()->subHours(4),
            ],
            [
                'filename' => 'backup_2026-02-26_22-45-15_ghi11111.zip',
                'path' => 'backups/',
                'size' => 62914560, // 60 MB
                'status' => 'completed',
                'log_message' => 'Backup completed successfully. Database and files backed up.',
                'created_by' => $superadmin->id,
                'completed_at' => now()->subDays(1),
            ],
            [
                'filename' => 'backup_2026-02-26_18-20-00_jkl22222.zip',
                'path' => 'backups/',
                'size' => 0,
                'status' => 'failed',
                'log_message' => 'Backup failed: Unable to connect to database. Please check database credentials.',
                'created_by' => $superadmin->id,
                'completed_at' => now()->subDays(1)->subHours(6),
            ],
        ];

        foreach ($backups as $backup) {
            SystemBackup::create($backup);
        }
    }
}
