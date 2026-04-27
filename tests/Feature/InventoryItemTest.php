<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function inventory_item_created_by_is_auto_set_from_auth_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $item = InventoryItem::create([
            'name' => 'Test Item',
            'category' => 'Electronics',
            'quantity' => 10,
            'unit' => 'pcs',
            'purchase_price' => 100.00,
            'supplier' => 'Test Supplier',
            'location' => 'Warehouse A',
            'status' => 'Active',
        ]);

        $this->assertNotNull($item->created_by);
        $this->assertEquals($user->id, $item->created_by);
    }
}
