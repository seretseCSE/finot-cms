<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RehearsalAttendanceFactory extends Factory
{
    protected $model = RehearsalAttendance::class;

    public function definition(): array
    {
        return [
            'rehearsal_id' => Rehearsal::factory(),
            'member_id' => Member::factory(),
            'status' => $this->faker->randomElement(['Present', 'Absent', 'Excused', 'Late', 'Permission']),
            'marked_by' => User::factory(),
            'marked_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Present',
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Absent',
        ]);
    }

    public function excused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Excused',
        ]);
    }
}
