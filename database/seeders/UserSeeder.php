<?php

namespace Database\Seeders;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * One admin-panel user per staff role (student accounts are provisioned separately).
     *
     * @return array<string, array{name: string, department_id: int|null, language_preference: string}>
     */
    protected function roleUsers(): array
    {
        return [
            Roles::SUPERADMIN => [
                'name' => 'Super Admin User',
                'department_id' => null,
                'language_preference' => 'en',
            ],
            Roles::ADMIN => [
                'name' => 'Admin User',
                'department_id' => null,
                'language_preference' => 'en',
            ],
            Roles::HR_HEAD => [
                'name' => 'HR Head User',
                'department_id' => 1, // Internal Relations
                'language_preference' => 'am',
            ],
            Roles::FINANCE_HEAD => [
                'name' => 'Finance Head User',
                'department_id' => 2, // Nibret ena Hisab
                'language_preference' => 'am',
            ],
            Roles::NIBRET_HISAB_HEAD => [
                'name' => 'Nibret Hisab Head User',
                'department_id' => 2,
                'language_preference' => 'am',
            ],
            Roles::INVENTORY_STAFF => [
                'name' => 'Inventory Staff User',
                'department_id' => 2,
                'language_preference' => 'am',
            ],
            Roles::EDUCATION_HEAD => [
                'name' => 'Education Head User',
                'department_id' => 3, // Education
                'language_preference' => 'am',
            ],
            Roles::EDUCATION_MONITOR => [
                'name' => 'Education Monitor User',
                'department_id' => 3,
                'language_preference' => 'am',
            ],
            Roles::DATA_ENCODER => [
                'name' => 'Data Encoder User',
                'department_id' => 3,
                'language_preference' => 'am',
            ],
            Roles::WORSHIP_MONITOR => [
                'name' => 'Worship Monitor User',
                'department_id' => 5, // Mezmur
                'language_preference' => 'am',
            ],
            Roles::MEZMUR_HEAD => [
                'name' => 'Mezmur Head User',
                'department_id' => 5,
                'language_preference' => 'am',
            ],
            Roles::AV_HEAD => [
                'name' => 'AV Head User',
                'department_id' => 1,
                'language_preference' => 'am',
            ],
            Roles::CHARITY_HEAD => [
                'name' => 'Charity Head User',
                'department_id' => 4, // Revenue & Charity
                'language_preference' => 'am',
            ],
            Roles::TOUR_HEAD => [
                'name' => 'Tour Head User',
                'department_id' => 4,
                'language_preference' => 'am',
            ],
            Roles::REVENUE_AND_CHARITY_HEAD => [
                'name' => 'Revenue and Charity Head User',
                'department_id' => 4,
                'language_preference' => 'am',
            ],
            Roles::INTERNAL_RELATIONS_HEAD => [
                'name' => 'Internal Relations Head User',
                'department_id' => 1,
                'language_preference' => 'am',
            ],
        ];
    }

    public function run(): void
    {
        $roleUsers = $this->roleUsers();
        $emailsToSeed = array_map(
            fn (string $role): string => "{$role}@finot.org",
            array_keys($roleUsers)
        );

        // Remove deprecated seeder accounts that are no longer part of the role map.
        $deprecatedEmails = [
            'department_secretary@finot.org',
            'staff@finot.org',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('users')->whereIn('email', [...$emailsToSeed, ...$deprecatedEmails])->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $phoneCounter = 1;

        foreach ($roleUsers as $roleName => $profile) {
            $email = "{$roleName}@finot.org";
            $phone = '+251911'.str_pad((string) $phoneCounter, 6, '0', STR_PAD_LEFT);

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $profile['name'],
                    'phone' => $phone,
                    'password' => 'Admin1234',
                    'is_active' => true,
                    'is_locked' => false,
                    'failed_login_attempts' => 0,
                    'temp_password_changed' => false,
                    'password_history' => [],
                    'language_preference' => $profile['language_preference'],
                    'department_id' => $profile['department_id'],
                ]
            );

            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $user->syncRoles([$role]);
            }

            $phoneCounter++;
        }

        $count = count($roleUsers);
        $this->command->info("Created {$count} role test users successfully.");
        $this->command->info('Login with 9 digits after +251. Super Admin: 911000001 / Admin1234');
        $this->command->info('You must change the password on first login.');
        $this->command->info('Email format: {role}@finot.org (e.g. superadmin@finot.org)');
    }
}
