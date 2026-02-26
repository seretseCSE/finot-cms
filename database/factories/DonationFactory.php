<?php

namespace Database\Factories;

use App\Models\Donation;
use Illuminate\Database\Eloquent\Factories\Factory;

class DonationFactory extends Factory
{
    protected $model = Donation::class;

    public function definition(): array
    {
        return [
            'donor_name' => $this->faker->optional(0.3)->name, // 30% chance of null (Anonymous)
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'donation_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'donation_type' => $this->faker->randomElement([
                'General Fund', 'Building Fund', 'Missionary Support', 'Charity/Aid', 'Other'
            ]),
            'custom_donation_type' => $this->faker->optional(0.2)->word, // 20% chance for custom type
            'notes' => $this->faker->optional()->sentence,
            'recorded_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'donor_name' => null,
        ]);
    }

    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'donor_name' => $name,
        ]);
    }

    public function forType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_type' => $type,
        ]);
    }

    public function generalFund(): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_type' => 'General Fund',
        ]);
    }

    public function buildingFund(): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_type' => 'Building Fund',
        ]);
    }

    public function missionarySupport(): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_type' => 'Missionary Support',
        ]);
    }

    public function charityAid(): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_type' => 'Charity/Aid',
        ]);
    }

    public function other(string $customType = null): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_type' => 'Other',
            'custom_donation_type' => $customType ?? $this->faker->word,
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    public function recent(int $days = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_date' => now()->subDays(rand(0, $days))->format('Y-m-d'),
            'created_at' => now()->subDays(rand(0, $days)),
        ]);
    }

    public function old(int $months = 6): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_date' => now()->subMonths(rand(1, $months))->format('Y-m-d'),
            'created_at' => now()->subMonths(rand(1, $months)),
        ]);
    }

    public function thisYear(): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_date' => $this->faker->dateTimeBetween('January 1 this year', 'now')->format('Y-m-d'),
        ]);
    }

    public function lastYear(): static
    {
        return $this->state(fn (array $attributes) => [
            'donation_date' => $this->faker->dateTimeBetween('January 1 last year', 'December 31 last year')->format('Y-m-d'),
        ]);
    }
}
