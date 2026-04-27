<?php

namespace Database\Factories;

use App\Models\FAQ;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FAQFactory extends Factory
{
    protected $model = FAQ::class;

    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence(6).'?',
            'question_am' => $this->faker->optional(0.5)->sentence(6).'?',
            'answer' => $this->faker->paragraphs(2, true),
            'answer_am' => $this->faker->optional(0.3)->paragraphs(2, true),
            'display_order' => $this->faker->numberBetween(1, 100),
            'is_featured' => $this->faker->boolean(20),
            'is_active' => true,
            'created_by' => User::factory(),
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
}
