<?php

namespace Database\Factories;

use App\Models\Rehearsal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RehearsalFactory extends Factory
{
    protected $model = Rehearsal::class;

    public function definition(): array
    {
        return [
            'date_time' => $this->faker->dateTimeBetween('+1 day', '+3 months'),
            'location' => $this->faker->randomElement(['Church Hall', 'Room 3', 'Main Auditorium', 'Basement', 'Garden']),
            'status' => $this->faker->randomElement(['Scheduled', 'Completed', 'Cancelled']),
            'recurrence_type' => 'None',
            'recurrence_end_date' => null,
            'songs' => null,
            'notes' => $this->faker->optional(0.5)->paragraph(),
            'created_by' => User::factory(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Scheduled',
            'date_time' => $this->faker->dateTimeBetween('+1 day', '+3 months'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
            'date_time' => $this->faker->dateTimeBetween('-3 months', '-1 day'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Cancelled',
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'recurrence_type' => $this->faker->randomElement(['Weekly', 'Biweekly', 'Monthly']),
            'recurrence_end_date' => $this->faker->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'),
        ]);
    }
}
