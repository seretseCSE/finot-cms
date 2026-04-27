<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        return [
            'place' => $this->faker->city(),
            'description' => $this->faker->paragraph(),
            'tour_date' => $this->faker->dateTimeBetween('+1 week', '+3 months'),
            'start_time' => $this->faker->time('H:i'),
            'cost_per_person' => $this->faker->optional(0.7)->randomFloat(2, 50, 500),
            'registration_deadline' => $this->faker->optional(0.8)->dateTimeBetween('now', '+2 weeks'),
            'max_capacity' => $this->faker->optional(0.6)->numberBetween(20, 100),
            'status' => 'Draft',
            'created_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Published',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Draft',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Cancelled',
        ]);
    }

    public function openRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Published',
            'registration_deadline' => $this->faker->dateTimeBetween('+1 day', '+2 weeks'),
            'max_capacity' => 50,
        ]);
    }

    public function closedRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Published',
            'registration_deadline' => $this->faker->dateTimeBetween('-2 weeks', '-1 day'),
        ]);
    }

    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Published',
            'max_capacity' => 2,
        ]);
    }
}
