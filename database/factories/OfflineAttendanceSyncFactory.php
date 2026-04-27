<?php

namespace Database\Factories;

use App\Models\OfflineAttendanceSync;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfflineAttendanceSyncFactory extends Factory
{
    protected $model = OfflineAttendanceSync::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'session_id' => 1,
            'student_id' => null,
            'member_id' => null,
            'status' => fake()->randomElement(['Present', 'Absent', 'Excused', 'Late', 'Permission']),
            'marked_at' => now(),
            'sync_status' => 'pending',
            'synced_at' => null,
            'conflict_reason' => null,
        ];
    }

    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);
    }

    public function conflict(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => 'conflict',
            'conflict_reason' => 'Session locked',
        ]);
    }
}
