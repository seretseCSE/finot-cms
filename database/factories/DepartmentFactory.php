<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'name_en' => $this->faker->unique()->company(),
            'name_am' => $this->faker->company(),
            'description' => $this->faker->optional(0.5)->paragraph(),
            'icon' => 'heroicon-o-building-office',
            'is_active' => true,
        ];
    }
}
