<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourPassenger;
use Illuminate\Database\Eloquent\Factories\Factory;

class TourPassengerFactory extends Factory
{
    protected $model = TourPassenger::class;

    public function definition(): array
    {
        return [
            'passenger_code' => config('finot.tour_passenger_code_prefix', 'TP-').str_pad($this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'tour_id' => Tour::factory(),
            'full_name' => $this->faker->name(),
            'phone' => config('finot.phone_prefix', '+251').'9'.$this->faker->numerify('########'),
            'passenger_count' => $this->faker->numberBetween(1, 4),
            'receipt_image' => null,
            'member_id' => null,
            'registration_type' => 'Public',
            'status' => 'Pending',
            'registration_date' => $this->faker->date(),
            'registered_by' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Confirmed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Cancelled',
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_type' => 'Internal',
        ]);
    }
}
