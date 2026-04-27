<?php

namespace Database\Factories;

use App\Models\TeacherAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherAttendanceFactory extends Factory
{
    protected $model = TeacherAttendance::class;

    public function definition(): array
    {
        return [
            'teacher_id' => 1,
            'attendance_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'status' => $this->faker->randomElement(['Present', 'Absent', 'Late', 'Excused']),
            'notes' => $this->faker->optional()->sentence,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
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

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Late',
        ]);
    }

    public function excused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Excused',
        ]);
    }
}
