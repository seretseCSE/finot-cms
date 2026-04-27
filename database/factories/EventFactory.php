<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'date_time' => $this->faker->dateTimeBetween('now', '+6 months'),
            'location' => $this->faker->address(),
            'description' => $this->faker->paragraph(),
            'featured_image' => null,
            'registration_required' => $this->faker->boolean(50),
            'max_capacity' => $this->faker->optional(0.7)->numberBetween(10, 500),
            'registration_deadline' => $this->faker->optional(0.7)->dateTimeBetween('now', '+5 months'),
            'status' => $this->faker->randomElement(['Draft', 'Published', 'Full', 'Ongoing', 'Completed', 'Cancelled']),
            'recurrence_type' => $this->faker->randomElement(['None', 'Weekly', 'Monthly', 'Custom']),
            'recurrence_end_date' => $this->faker->optional(0.3)->dateTimeBetween('+1 month', '+12 months'),
            'parent_event_id' => null,
            'created_by' => function () {
                return auth()->check() ? auth()->id() : 1;
            }, // Use authenticated user or fallback to admin
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

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Ongoing',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
        ]);
    }

    public function withRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_required' => true,
            'max_capacity' => 100,
            'registration_deadline' => $this->faker->dateTimeBetween('now', '+5 months'),
        ]);
    }
}
