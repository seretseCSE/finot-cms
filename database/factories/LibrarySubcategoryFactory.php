<?php

namespace Database\Factories;

use App\Models\LibraryCategory;
use App\Models\LibrarySubcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LibrarySubcategoryFactory extends Factory
{
    protected $model = LibrarySubcategory::class;

    public function definition(): array
    {
        return [
            'category_id' => LibraryCategory::factory(),
            'name' => $this->faker->words(2, true),
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
