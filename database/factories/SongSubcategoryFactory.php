<?php

namespace Database\Factories;

use App\Models\SongCategory;
use App\Models\SongSubcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SongSubcategoryFactory extends Factory
{
    protected $model = SongSubcategory::class;

    public function definition(): array
    {
        return [
            'category_id' => SongCategory::factory(),
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
