<?php

namespace Database\Factories;

use App\Models\PredefinedReport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PredefinedReportFactory extends Factory
{
    protected $model = PredefinedReport::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'resource_type' => fake()->randomElement(['members', 'contributions', 'attendance', 'donations']),
            'filter_criteria' => [],
            'columns' => null,
            'format' => fake()->randomElement(['screen', 'excel', 'pdf', 'csv']),
            'is_active' => true,
            'created_by' => null,
            'display_order' => fake()->numberBetween(0, 100),
        ];
    }
}
