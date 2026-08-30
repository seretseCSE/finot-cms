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
            StudentUserSeeder::class,
            TeacherSubjectAssignmentSeeder::class,

            // Content Management
            PageSeeder::class,
            BlogSeeder::class,
            MediaSeeder::class,
            DocumentSeeder::class,
            TourSeeder::class,

            ContactMessageSeeder::class,

            // Library Data
            LibrarySampleDataSeeder::class,

            // Specialized Data
            EthiopianOrthodoxSampleDataSeeder::class,
            SystemBackupsSeeder::class,
        ]);

        if (filter_var(env('SEED_ATTENDANCE_TEST_DATA', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(AttendanceTestDataSeeder::class);
        }
    }
}
