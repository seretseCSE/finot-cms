<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'item_code' => null,
            'name' => $this->faker->word(),
            'category' => $this->faker->randomElement(['Electronics', 'Furniture', 'Books', 'Supplies', 'Equipment', 'Other']),
            'quantity' => $this->faker->numberBetween(1, 100),
            'unit' => $this->faker->randomElement(['pieces', 'boxes', 'sets', 'kg', 'liters', 'Other']),
            'purchase_date' => $this->faker->optional()->date(),
            'purchase_price' => $this->faker->optional()->randomFloat(2, 10, 1000),
            'supplier' => $this->faker->optional()->company(),
            'location' => $this->faker->optional()->word(),
            'status' => 'Active',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Damaged',
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Lost',
        ]);
    }

    public function disposed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Disposed',
        ]);
    }
}
