<?php

namespace Database\Factories;

use App\Models\TourAttendance;
use App\Models\TourAttendanceSession;
use App\Models\TourPassenger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TourAttendanceFactory extends Factory
{
    protected $model = TourAttendance::class;

    public function definition(): array
    {
        return [
            'session_id' => TourAttendanceSession::factory(),
            'passenger_id' => TourPassenger::factory(),
            'status' => $this->faker->randomElement(['Present', 'Not Present']),
            'marked_at' => now(),
            'marked_by' => User::factory(),
            'notes' => $this->faker->optional()->sentence,
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Present',
        ]);
    }

    public function notPresent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Not Present',
        ]);
    }
}
