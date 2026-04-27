<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'title_am' => $this->faker->optional(0.5)->sentence(3),
            'slug' => null,
            'content' => $this->faker->paragraphs(5, true),
            'content_am' => $this->faker->optional(0.3)->paragraphs(5, true),
            'status' => $this->faker->randomElement(['Draft', 'Published', 'Archived']),
            'meta_description' => $this->faker->optional(0.5)->sentence(6),
            'meta_description_am' => $this->faker->optional(0.3)->sentence(6),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Draft',
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Published',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Archived',
        ]);
    }
}
