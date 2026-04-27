<?php

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourAttendanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TourAttendanceSessionFactory extends Factory
{
    protected $model = TourAttendanceSession::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'session_date' => $this->faker->date(),
            'status' => 'Open',
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Completed',
        ]);
    }
}
