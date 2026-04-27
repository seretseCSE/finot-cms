<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentArchiveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_access_documents_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/documents');
        $response->assertStatus(200);
    }

    #[Test]
    public function admin_can_access_documents_create_page(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/documents/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function archives_search_page_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/archives-search');
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
    public function library_resource_pages_accessible(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $this->get('/admin/libraries')->assertStatus(200);
        $this->get('/admin/libraries/create')->assertStatus(200);
    }

    #[Test]
    public function public_library_page_loads(): void
    {
        $response = $this->get('/library');
        $response->assertStatus(200);
    }

    #[Test]
    public function contact_messages_visible_to_admin(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->get('/admin/contact-messages');
        $response->assertStatus(200);
    }

    #[Test]
    public function document_model_can_be_deleted(): void
    {
        $doc = Document::factory()->create();
        $doc->delete();
        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    }
}
