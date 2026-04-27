<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'teacher_code' => 'TCH-'.fake()->unique()->numberBetween(1000, 9999),
            'full_name' => fake()->name(),
            'phone' => config('finot.phone_prefix', '+251').fake()->numberBetween(911000001, 999999999),
            'qualifications' => fake()->sentence(),
            'status' => 'Active',
            'created_by' => User::factory(),
        ];
    }
}
