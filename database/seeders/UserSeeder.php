<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing users (only from this seeder's emails)
        $emailsToSeed = [
            'superadmin@finot.org',
            'admin@finot.org',
            'hr_head@finot.org',
            'finance_head@finot.org',
            'nibret_hisab_head@finot.org',
            'inventory_staff@finot.org',
            'education_head@finot.org',
            'education_monitor@finot.org',
            'worship_monitor@finot.org',
            'mezmur_head@finot.org',
            'av_head@finot.org',
            'charity_head@finot.org',
            'tour_head@finot.org',
            'internal_relations_head@finot.org',
            'department_secretary@finot.org',
            'staff@finot.org',
        ];

        // Temporarily disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Delete only users that match our seeder emails
        DB::table('users')->whereIn('email', $emailsToSeed)->delete();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create 16 test users
        $users = [
            [
                'name' => 'Super Admin User',
                'email' => 'superadmin@finot.org',
                'phone' => '+251911000001',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'en',
                'department_id' => null, // No department for superadmin
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@finot.org',
                'phone' => '+251911000002',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'en',
                'department_id' => null, // No department for admin
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'HR Head User',
                'email' => 'hr_head@finot.org',
                'phone' => '+251911000003',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 1, // Internal Relations
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Finance Head User',
                'email' => 'finance_head@finot.org',
                'phone' => '+251911000004',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 2, // Nibret ena Hisab
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Nibret Hisab Head User',
                'email' => 'nibret_hisab_head@finot.org',
                'phone' => '+251911000005',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 2, // Nibret ena Hisab
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Inventory Staff User',
                'email' => 'inventory_staff@finot.org',
                'phone' => '+251911000006',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 2, // Nibret ena Hisab
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Education Head User',
                'email' => 'education_head@finot.org',
                'phone' => '+251911000007',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 3, // Education
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Education Monitor User',
                'email' => 'education_monitor@finot.org',
                'phone' => '+251911000008',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 3, // Education
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Worship Monitor User',
                'email' => 'worship_monitor@finot.org',
                'phone' => '+251911000009',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 5, // Mezmur
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mezmur Head User',
                'email' => 'mezmur_head@finot.org',
                'phone' => '+251911000010',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 5, // Mezmur
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'AV Head User',
                'email' => 'av_head@finot.org',
                'phone' => '+251911000011',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 1, // Internal Relations
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Charity Head User',
                'email' => 'charity_head@finot.org',
                'phone' => '+251911000012',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 4, // Revenue & Charity
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tour Head User',
                'email' => 'tour_head@finot.org',
                'phone' => '+251911000013',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 4, // Revenue & Charity
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Internal Relations Head User',
                'email' => 'internal_relations_head@finot.org',
                'phone' => '+251911000014',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 1, // Internal Relations
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Department Secretary User',
                'email' => 'department_secretary@finot.org',
                'phone' => '+251911000015',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 3, // Education (example as specified)
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Staff User',
                'email' => 'staff@finot.org',
                'phone' => '+251911000016',
                'password' => Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => false,
                'password_history' => [],
                'language_preference' => 'am',
                'department_id' => 3, // Education (example as specified)
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert all users
        foreach ($users as $userData) {
            User::create($userData);
        }

        // Assign roles to users
        $roleAssignments = [
            'superadmin@finot.org' => 'superadmin',           // Super Admin User
            'admin@finot.org' => 'admin',               // Admin User
            'hr_head@finot.org' => 'hr_head',            // HR Head User
            'finance_head@finot.org' => 'finance_head',         // Finance Head User
            'nibret_hisab_head@finot.org' => 'nibret_hisab_head',   // Nibret Hisab Head User
            'inventory_staff@finot.org' => 'inventory_staff',      // Inventory Staff User
            'education_head@finot.org' => 'education_head',       // Education Head User
            'education_monitor@finot.org' => 'education_monitor',    // Education Monitor User
            'worship_monitor@finot.org' => 'worship_monitor',     // Worship Monitor User
            'mezmur_head@finot.org' => 'mezmur_head',        // Mezmur Head User
            'av_head@finot.org' => 'av_head',            // AV Head User
            'charity_head@finot.org' => 'charity_head',        // Charity Head User
            'tour_head@finot.org' => 'tour_head',          // Tour Head User
            'internal_relations_head@finot.org' => 'internal_relations_head', // Internal Relations Head User
            'revenue_and_charity_head@finot.org' => 'revenue_and_charity_head', // Revenue and Charity Head User
        ];

        foreach ($roleAssignments as $email => $roleName) {
            $user = User::where('email', $email)->first();
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
            if ($user && $role) {
                // Remove existing roles and assign new one
                $user->roles()->detach();
                $user->assignRole($role);
            }
        }

        $this->command->info('Created 16 test users successfully.');
        $this->command->info('All users have temp_password_changed = false and will be required to change password on first login.');
        $this->command->info('Default password is "Admin1234"');
    }
}
