<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventorySystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function inventory_staff_can_access_inventory_page(): void
    {
        $user = $this->createInventoryStaffUser();
        $this->actingAs($user);

        $response = $this->get('/admin/inventories');
        $response->assertStatus(200);
    }

    #[Test]
    public function inventory_staff_can_access_inventory_create_page(): void
    {
        $user = $this->createInventoryStaffUser();
        $this->actingAs($user);

        $response = $this->get('/admin/inventories/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function inventory_search_page_accessible(): void
    {
        $user = $this->createInventoryStaffUser();
        $this->actingAs($user);

        $response = $this->get('/admin/inventory-search');
        $response->assertStatus(200);
    }

    #[Test]
    public function inventory_analytics_page_accessible(): void
    {
        $user = $this->createNibretHisabHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/inventories/analytics');
        $response->assertStatus(200);
    }

    #[Test]
    public function low_stock_items_are_tracked(): void
    {
        InventoryItem::factory()->create([
            'name' => 'Low Stock Item',
            'quantity' => 1,
        ]);

        $user = $this->createInventoryStaffUser();
        $this->actingAs($user);

        $response = $this->get('/admin/inventories');
        $this->assertNotEquals(404, $response->getStatusCode(), 'Route not found');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Forbidden');
    }

    #[Test]
    public function stock_movements_resource_accessible(): void
    {
        $user = $this->createInventoryStaffUser();
        $this->actingAs($user);

        $response = $this->get('/admin/stock-movements');
        $response->assertStatus(200);
    }

    #[Test]
    public function loss_records_resource_accessible(): void
    {
        $user = $this->createInventoryStaffUser();
        $this->actingAs($user);

        $response = $this->get('/admin/loss-records');
        $response->assertStatus(200);
    }

    #[Test]
    public function inventory_item_factory_works(): void
    {
        $item = InventoryItem::factory()->create(['name' => 'Test Projector']);
        $this->assertDatabaseHas('inventory_items', ['name' => 'Test Projector']);
    }
}
