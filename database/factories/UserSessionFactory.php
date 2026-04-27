<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserSession>
 */
class UserSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'session_token' => $this->faker->uuid,
            'device_info' => $this->faker->userAgent,
            'ip_address' => $this->faker->ipv4,
            'last_activity' => now(),
        ];
    }
}
