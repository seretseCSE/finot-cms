<?php

namespace Database\Factories;

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficiaryFactory extends Factory
{
    protected $model = Beneficiary::class;

    public function definition(): array
    {
        return [
            'beneficiary_code' => 'B-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'full_name' => fake()->name(),
            'phone' => config('finot.phone_prefix', '+251').fake()->numerify('#########'),
            'address' => fake()->address(),
            'type' => fake()->randomElement(['Individual', 'Family', 'Organization']),
            'need_category' => fake()->randomElement(['Food', 'Medical', 'Education', 'Housing', 'Other']),
            'email' => fake()->optional()->safeEmail(),
            'id_number' => fake()->optional()->numerify('ID-########'),
            'dependents_count' => fake()->optional()->numberBetween(0, 10),
            'monthly_income' => fake()->optional()->randomFloat(2, 0, 10000),
            'notes' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['Active', 'Inactive', 'Completed']),
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Inactive',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
        ]);
    }
}
