<?php

namespace Database\Factories;

use App\Models\MediaCategory;
use App\Models\MediaSubcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaSubcategoryFactory extends Factory
{
    protected $model = MediaSubcategory::class;

    public function definition(): array
    {
        return [
            'category_id' => MediaCategory::factory(),
            'name' => $this->faker->unique()->word(),
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
