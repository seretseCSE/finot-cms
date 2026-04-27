<?php

namespace Database\Factories;

use App\Models\LibraryCategory;
use App\Models\LibraryResource;
use App\Models\LibrarySubcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LibraryResourceFactory extends Factory
{
    protected $model = LibraryResource::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'file_path' => $this->faker->uuid().'.pdf',
            'file_type' => 'pdf',
            'category_id' => LibraryCategory::factory(),
            'subcategory_id' => null,
            'description' => $this->faker->paragraph(),
            'is_featured' => false,
            'is_active' => true,
            'file_size_kb' => $this->faker->numberBetween(100, 5000),
            'uploaded_by' => User::factory(),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withSubcategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'subcategory_id' => LibrarySubcategory::factory(),
        ]);
    }
}
