<?php

namespace Tests\Feature;

use App\Models\LibraryCategory;
use App\Models\LibraryResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LibrarySystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_access_library_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/libraries');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_library_create_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/libraries/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function library_categories_resource_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/library-categories');
        $response->assertStatus(200);
    }

    #[Test]
    public function library_subcategories_resource_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/library-subcategories');
        $response->assertStatus(200);
    }

    #[Test]
    public function public_library_page_loads(): void
    {
        $response = $this->get('/library');
        $response->assertStatus(200);
    }

    #[Test]
    public function library_resource_model_works(): void
    {
        $category = LibraryCategory::factory()->create();
        $resource = LibraryResource::factory()->create([
            'category_id' => $category->id,
            'title' => 'Test Sermon',
        ]);

        $this->assertDatabaseHas('library_resources', ['title' => 'Test Sermon']);
    }

    #[Test]
    public function library_category_model_works(): void
    {
        $category = LibraryCategory::factory()->create(['name' => 'Sermons']);
        $this->assertDatabaseHas('library_categories', ['name' => 'Sermons']);
    }
}
