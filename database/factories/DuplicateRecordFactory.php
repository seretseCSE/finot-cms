<?php

namespace Database\Factories;

use App\Models\DuplicateRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class DuplicateRecordFactory extends Factory
{
    protected $model = DuplicateRecord::class;

    public function definition(): array
    {
        return [
            'model_type' => 'App\Models\Member',
            'primary_record_id' => 1,
            'duplicate_record_id' => 2,
            'match_criteria' => ['phone' => fake()->phoneNumber()],
            'status' => 'pending',
            'notes' => null,
        ];
    }

    public function merged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'merged',
            'merged_at' => now(),
            'merged_by' => 1,
        ]);
    }

    public function ignored(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ignored',
        ]);
    }
}
