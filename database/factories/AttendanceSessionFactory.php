<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    public function definition(): array
    {
        return [
            'school_class_id' => 1,
            'session_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'status' => $this->faker->randomElement(['Open', 'Completed', 'Locked', 'Cancelled']),
            'notes' => $this->faker->optional()->sentence,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Open',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Locked',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Cancelled',
        ]);
    }

    public function old(int $days = 35): static
    {
        return $this->state(fn (array $attributes) => [
            'session_date' => now()->subDays($days),
            'created_at' => now()->subDays($days),
        ]);
    }

    public function recent(int $days = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'session_date' => now()->subDays($days),
            'created_at' => now()->subDays($days),
        ]);
    }
}
