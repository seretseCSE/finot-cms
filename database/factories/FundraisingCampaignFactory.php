<?php

namespace Database\Factories;

use App\Models\FundraisingCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FundraisingCampaignFactory extends Factory
{
    protected $model = FundraisingCampaign::class;

    public function definition(): array
    {
        $target = $this->faker->randomFloat(2, 10000, 500000);
        $raised = $this->faker->randomFloat(2, 0, $target);

        return [
            'campaign_name' => $this->faker->sentence(3),
            'target_amount' => $target,
            'total_raised' => $raised,
            'start_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'end_date' => $this->faker->optional(0.7)->dateTimeBetween('now', '+6 months'),
            'description' => $this->faker->paragraph(),
            'featured_image' => null,
            'campaign_category' => $this->faker->randomElement(['Building', 'Missionary', 'Charity', 'General']),
            'bank_account_info' => $this->faker->optional()->sentence(),
            'status' => $this->faker->randomElement(['Active', 'Completed', 'Draft', 'Cancelled']),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Active',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
            'end_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Draft',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Cancelled',
        ]);
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured_image' => $this->faker->uuid().'.jpg',
        ]);
    }
}
