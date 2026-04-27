<?php

namespace Database\Factories;

use App\Models\LibraryCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LibraryCategoryFactory extends Factory
{
    protected $model = LibraryCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'display_order' => $this->faker->numberBetween(0, 100),
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
