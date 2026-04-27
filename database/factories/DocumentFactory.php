<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'file_path' => $this->faker->uuid().'.pdf',
            'file_size_kb' => $this->faker->numberBetween(100, 5000),
            'file_type' => 'pdf',
            'description' => $this->faker->paragraph(),
            'tags' => implode(', ', $this->faker->words(3)),
            'document_date' => $this->faker->date(),
            'visibility' => $this->faker->randomElement(['Public', 'Members Only', 'Department Only']),
            'department_id' => Department::factory(),
            'uploaded_by' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'Public',
        ]);
    }

    public function membersOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'Members Only',
        ]);
    }

    public function departmentOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'Department Only',
        ]);
    }

    public function forDepartment(int $departmentId): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $departmentId,
        ]);
    }

    public function uploadedBy(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_by' => $userId,
        ]);
    }
}
