<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'account_number' => fake()->unique()->numerify('###################'),
            'account_name' => fake()->company().' Account',
            'bank_name' => fake()->randomElement(['Commercial Bank of Ethiopia', 'Dashen Bank', 'Awash Bank', 'Bank of Abyssinia']),
            'branch_name' => fake()->city().' Branch',
            'account_type' => fake()->randomElement(['checking', 'savings', 'investment']),
            'current_balance' => fake()->randomFloat(2, 0, 100000),
            'currency' => 'ETB',
            'phone_number' => config('finot.phone_prefix', '+251').fake()->numberBetween(911000001, 999999999),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
