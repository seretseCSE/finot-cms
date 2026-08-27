<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Membership;
use App\Models\User;
use App\Support\PublicId;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(GradeLevelSeeder::class);
        $this->call(SubjectSeeder::class);
        $this->call(BankSeeder::class);
        $this->call(SchoolDirectorySeeder::class);
        $this->call(HealthConditionSeeder::class);
        $this->call(GradingScaleSeeder::class);
        $this->call(InventoryCategorySeeder::class);

        $superAdmin = User::firstOrCreate(
            ['phone' => env('SUPER_ADMIN_PHONE', '0911000000')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Temari Super Admin'),
                'email' => env('SUPER_ADMIN_EMAIL', 'admin@temari.et'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', '12345678')),
                'email_verified_at' => now(),
                'preferred_language' => 'en',
                'status' => AccountStatus::Active,
            ],
        );

        // WithoutModelEvents mutes the creating hook that assigns public ids.
        if ($superAdmin->public_id === null) {
            $superAdmin->forceFill(['public_id' => PublicId::generate('users')])->save();
        }

        // A platform membership is the sole record of the role (ADR-010).
        Membership::firstOrCreate(
            [
                'user_id' => $superAdmin->id,
                'school_id' => null,
                'branch_id' => null,
                'role' => RoleEnum::SuperAdmin->value,
            ],
            [
                'scope' => RoleEnum::SuperAdmin->scope()->value,
                'is_active' => true,
                'joined_at' => now(),
            ],
        );
    }
}
