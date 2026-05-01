<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core System Seeders
            DepartmentSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            SiteSettingsSeeder::class,
            CustomOptionsSeeder::class,

            // Member Management
            MemberSeeder::class,
            TeacherSeeder::class,
            SubjectSeeder::class,
            ClassSeeder::class,
            TeacherSubjectAssignmentSeeder::class,

            // Content Management
            PageSeeder::class,
            BlogSeeder::class,
            MediaSeeder::class,
            DocumentSeeder::class,
            TourSeeder::class,

            ContactMessageSeeder::class,

            // Specialized Data
            EthiopianOrthodoxSampleDataSeeder::class,
            PredefinedReportSeeder::class,
            SystemBackupsSeeder::class,
        ]);

        // Call attendance test data seeder for testing bulk attendance features
        if ($this->command->confirm('Do you want to create attendance test data for bulk attendance testing?', false)) {
            $this->call(AttendanceTestDataSeeder::class);
        }
    }
}
