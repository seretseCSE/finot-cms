<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\MediaCategory;
use App\Models\MediaItem;
use App\Models\MediaSubcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaItemFactory extends Factory
{
    protected $model = MediaItem::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['Photo', 'Video']);
        $filePath = $type === 'Photo'
            ? 'media/photos/'.$this->faker->uuid().'.jpg'
            : 'media/videos/'.$this->faker->uuid().'.mp4';

        return [
            'title' => $this->faker->sentence(3),
            'type' => $type,
            'category_id' => MediaCategory::factory(),
            'subcategory_id' => MediaSubcategory::factory(),
            'description' => $this->faker->paragraph(),
            'file_path' => $filePath,
            'file_size_kb' => $type === 'Photo'
                ? $this->faker->numberBetween(100, 5000)
                : $this->faker->numberBetween(1000, 50000),
            'event_album' => $this->faker->optional(0.3)->words(2, true),
            'tags' => implode(',', $this->faker->words(3)),
            'visibility' => $this->faker->randomElement(['Public', 'Members Only', 'Department Only']),
            'department_id' => Department::factory(),
            'uploaded_by' => User::factory(),
        ];
    }

    public function photo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Photo',
            'file_path' => 'media/photos/'.$this->faker->uuid().'.jpg',
            'file_size_kb' => $this->faker->numberBetween(100, 5000),
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Video',
            'file_path' => 'media/videos/'.$this->faker->uuid().'.mp4',
            'file_size_kb' => $this->faker->numberBetween(1000, 50000),
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'Public',
        ]);
    }

    public function membersOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'Members Only',
        ]);
    }

    public function departmentOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'Department Only',
        ]);
    }
}
