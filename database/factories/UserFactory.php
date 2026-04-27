<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => config('finot.phone_prefix', '+251').fake()->numberBetween(911000001, 999999999),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
            'is_locked' => false,
            'temp_password_changed' => true,
            'failed_login_attempts' => 0,
            'language_preference' => 'en',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withRole(string $roleName): static
    {
        return $this->afterCreating(function (User $user) use ($roleName): void {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['label' => ucwords(str_replace('_', ' ', $roleName))]
            );
            $user->assignRole($role);
        });
    }

    public function withDepartment(string $departmentName): static
    {
        return $this->afterCreating(function (User $user) use ($departmentName): void {
            $department = Department::firstOrCreate(
                ['name_en' => $departmentName],
                [
                    'name_en' => $departmentName,
                    'name_am' => $departmentName,
                    'code' => strtoupper(substr($departmentName, 0, 3)),
                    'is_active' => true,
                ]
            );
            $user->update(['department_id' => $department->id]);
        });
    }

    public function superadmin(): static
    {
        return $this->withRole('superadmin');
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    public function hrHead(): static
    {
        return $this->withRole('hr_head');
    }

    public function financeHead(): static
    {
        return $this->withRole('finance_head');
    }

    public function nibretHisabHead(): static
    {
        return $this->withRole('nibret_hisab_head');
    }

    public function inventoryStaff(): static
    {
        return $this->withRole('inventory_staff');
    }

    public function educationHead(): static
    {
        return $this->withRole('education_head');
    }

    public function educationMonitor(): static
    {
        return $this->withRole('education_monitor');
    }

    public function worshipMonitor(): static
    {
        return $this->withRole('worship_monitor');
    }

    public function mezmurHead(): static
    {
        return $this->withRole('mezmur_head');
    }

    public function avHead(): static
    {
        return $this->withRole('av_head');
    }

    public function charityHead(): static
    {
        return $this->withRole('charity_head');
    }

    public function tourHead(): static
    {
        return $this->withRole('tour_head');
    }

    public function internalRelationsHead(): static
    {
        return $this->withRole('internal_relations_head');
    }

    public function departmentSecretary(string $departmentName = 'Internal Relations'): static
    {
        return $this->withRole('department_secretary')->withDepartment($departmentName);
    }

    public function staff(string $departmentName = 'Internal Relations'): static
    {
        return $this->withRole('staff')->withDepartment($departmentName);
    }

    public function needsPasswordChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'temp_password_changed' => false,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }
}
