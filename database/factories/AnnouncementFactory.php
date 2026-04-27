<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'title_am' => $this->faker->optional(0.5)->sentence(4),
            'content' => $this->faker->paragraphs(3, true),
            'content_am' => $this->faker->optional(0.3)->paragraphs(3, true),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->optional(0.7)->dateTimeBetween('+1 week', '+3 months')?->format('Y-m-d'),
            'is_urgent' => $this->faker->boolean(20),
            'status' => $this->faker->randomElement(['Active', 'Expired', 'Archived']),
            'is_global' => $this->faker->boolean(30),
            'target_audience' => $this->faker->randomElement(['all_users', 'admin_only', 'department_heads', 'specific_departments', 'specific_roles']),
            'broadcast_channels' => ['in_app'],
            'acknowledged_by' => [],
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Active',
            'start_date' => $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'end_date' => $this->faker->optional(0.7)->dateTimeBetween('+1 week', '+3 months')?->format('Y-m-d'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Expired',
            'start_date' => $this->faker->dateTimeBetween('-3 months', '-2 months')->format('Y-m-d'),
            'end_date' => $this->faker->dateTimeBetween('-1 month', '-1 day')->format('Y-m-d'),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Archived',
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_urgent' => true,
        ]);
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_global' => true,
        ]);
    }
}
