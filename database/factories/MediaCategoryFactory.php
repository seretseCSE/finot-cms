<?php

namespace Database\Factories;

use App\Models\MediaCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaCategoryFactory extends Factory
{
    protected $model = MediaCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'display_order' => $this->faker->numberBetween(1, 100),
            'status' => 'Active',
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Inactive',
        ]);
    }
}
