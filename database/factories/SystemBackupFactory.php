<?php

namespace Database\Factories;

use App\Models\SystemBackup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemBackupFactory extends Factory
{
    protected $model = SystemBackup::class;

    public function definition(): array
    {
        return [
            'filename' => SystemBackup::generateFilename(),
            'path' => 'backups/',
            'size' => 0,
            'status' => 'pending',
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }
}
