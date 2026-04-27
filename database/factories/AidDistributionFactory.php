<?php

namespace Database\Factories;

use App\Models\AidDistribution;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AidDistributionFactory extends Factory
{
    protected $model = AidDistribution::class;

    public function definition(): array
    {
        return [
            'beneficiary_id' => Beneficiary::factory(),
            'distribution_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'aid_type' => fake()->randomElement(['Cash', 'Food', 'Clothing', 'Medical', 'Education', 'Housing', 'Other']),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'distributed_by' => User::factory(),
            'receipt_number' => fake()->optional()->numerify('RCP-########'),
            'notes' => fake()->optional()->sentence(),
            'is_locked' => false,
            'locked_at' => null,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
            'locked_at' => now(),
        ]);
    }
}
