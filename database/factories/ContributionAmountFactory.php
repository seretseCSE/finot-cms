<?php

namespace Database\Factories;

use App\Models\ContributionAmount;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContributionAmountFactory extends Factory
{
    protected $model = ContributionAmount::class;

    public function definition(): array
    {
        return [
            'group_id' => 1,
            'month_name' => $this->faker->randomElement([
                'Meskerem', 'Tikimt', 'Hidar', 'Tahsas', 'Tir', 'Yekatit',
                'Megabit', 'Miazia', 'Ginbot', 'Sene', 'Hamle', 'Nehasse'
            ]),
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'effective_from' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'effective_to' => null, // Most are ongoing
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->subMonths(1)->format('Y-m-d'),
            'effective_to' => null,
        ]);
    }

    public function historical(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->subMonths(6)->format('Y-m-d'),
            'effective_to' => now()->subMonths(1)->format('Y-m-d'),
        ]);
    }

    public function forGroup(int $groupId): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $groupId,
        ]);
    }

    public function forMonth(string $monthName): static
    {
        return $this->state(fn (array $attributes) => [
            'month_name' => $monthName,
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }
}
