<?php

namespace Database\Factories;

use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentEnrollmentFactory extends Factory
{
    protected $model = StudentEnrollment::class;

    public function definition(): array
    {
        return [
            'member_id' => \App\Models\Member::factory(),
            'class_id' => \App\Models\SchoolClass::factory(),
            'academic_year_id' => \App\Models\AcademicYear::factory(),
            'enrolled_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status' => $this->faker->randomElement(['Enrolled', 'Withdrawn', 'Completed']),
            'completion_date' => null,
            'withdrawal_reason' => null,
            'withdrawal_notes' => null,
            'completed_by' => null,
            'enrolled_by' => \App\Models\User::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function enrolled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Enrolled',
            'completion_date' => null,
            'withdrawal_reason' => null,
            'withdrawal_notes' => null,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Withdrawn',
            'completion_date' => now(),
            'withdrawal_reason' => $this->faker->randomElement(['Moved Away', 'Transferred', 'Graduated']),
            'withdrawal_notes' => $this->faker->sentence,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
            'completion_date' => now(),
            'completed_by' => 1,
        ]);
    }
}
