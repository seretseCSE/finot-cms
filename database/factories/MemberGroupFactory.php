<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MemberGroup>
 */
class MemberGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'group_type' => $this->faker->randomElement(['Kids', 'Youth', 'Adults', 'Ministry']),
            'description' => $this->faker->sentence,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
