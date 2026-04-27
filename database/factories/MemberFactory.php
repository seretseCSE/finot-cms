<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Member::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'father_name' => fake()->lastName(),
            'grandfather_name' => fake()->lastName(),
            'mother_name' => fake()->firstName(),
            'phone' => config('finot.phone_prefix', '+251').fake()->numberBetween(911000001, 999999999),
            'email' => fake()->unique()->safeEmail(),
            'date_of_birth' => fake()->date(),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'city' => fake()->city(),
            'sub_city' => fake()->city(),
            'woreda' => fake()->numberBetween(1, 20),
            'zone' => fake()->city(),
            'block' => fake()->buildingNumber(),
            'neighborhood' => fake()->streetName(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => config('finot.phone_prefix', '+251').fake()->numberBetween(911000001, 999999999),
            'confession_father_name' => fake()->name(),
            'confession_father_phone' => config('finot.phone_prefix', '+251').fake()->numberBetween(911000001, 999999999),
            'member_type' => fake()->randomElement(['Kids', 'Youth', 'Adult']),
            'status' => 'Active',
        ];
    }
}
