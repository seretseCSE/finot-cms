<?php

namespace Database\Factories;

use App\Models\Contribution;
use App\Models\AcademicYear;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContributionFactory extends Factory
{
    protected $model = Contribution::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'amount' => $this->faker->randomFloat(2, 50, 2000),
            'month_name' => $this->faker->randomElement([
                'Meskerem', 'Tikimt', 'Hidar', 'Tahsas', 'Tir', 'Yekatit',
                'Megabit', 'Miazia', 'Ginbot', 'Sene', 'Hamle', 'Nehasse'
            ]),
            'payment_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'payment_method' => $this->faker->randomElement(['Cash', 'Check', 'Mobile Money', 'Bank Transfer']),
            'custom_payment_method' => null,
            'notes' => $this->faker->optional()->sentence,
            'recorded_by' => 1,
            'is_archived' => false,
            'archived_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function forMember(int $memberId): static
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => $memberId,
        ]);
    }

    public function forAcademicYear(int $academicYearId): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_year_id' => $academicYearId,
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

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
            'archived_at' => now()->subDays(rand(1, 30)),
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'Cash',
        ]);
    }

    public function mobileMoney(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'Mobile Money',
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'Bank Transfer',
        ]);
    }

    public function check(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'Check',
        ]);
    }

    public function other(string $customMethod = null): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'Other',
            'custom_payment_method' => $customMethod ?? $this->faker->word,
        ]);
    }

    public function recent(int $days = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_date' => now()->subDays(rand(0, $days))->format('Y-m-d'),
            'created_at' => now()->subDays(rand(0, $days)),
        ]);
    }

    public function old(int $months = 6): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_date' => now()->subMonths(rand(1, $months))->format('Y-m-d'),
            'created_at' => now()->subMonths(rand(1, $months)),
        ]);
    }
}
