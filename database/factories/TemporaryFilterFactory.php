<?php

namespace Database\Factories;

use App\Models\TemporaryFilter;
use Illuminate\Database\Eloquent\Factories\Factory;

class TemporaryFilterFactory extends Factory
{
    protected $model = TemporaryFilter::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'resource_type' => fake()->randomElement(['members', 'contributions', 'attendance', 'donations']),
            'filter_criteria' => [],
            'user_id' => 1,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
