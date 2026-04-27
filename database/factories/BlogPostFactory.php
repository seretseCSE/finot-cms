<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'title_am' => $this->faker->optional(0.5)->sentence(4),
            'content' => $this->faker->paragraphs(5, true),
            'content_am' => $this->faker->optional(0.3)->paragraphs(5, true),
            'author_id' => User::factory(),
            'publish_date' => $this->faker->optional(0.7)->date(),
            'featured_image' => $this->faker->optional(0.5)->word().'.jpg',
            'tags' => implode(',', $this->faker->words(4)),
            'status' => $this->faker->randomElement(['Draft', 'Scheduled', 'Published', 'Archived']),
            'published_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Draft',
            'publish_date' => null,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Scheduled',
            'publish_date' => $this->faker->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d'),
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Published',
            'publish_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Archived',
            'publish_date' => $this->faker->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
            'published_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }
}
