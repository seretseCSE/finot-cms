<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HttpMethodDemonstrationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    // GET REQUEST TESTS
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function get_index_returns_200_with_json_structure(): void
    {
        ContactMessage::factory()->count(3)->create();

        $response = $this->getJson(route('api.demo.contact-messages.index'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('X-Demo-Endpoint', 'list');
        $response->assertJsonStructure([
            'success',
            'count',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'subject',
                    'message',
                    'is_read',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 3);
    }

    #[Test]
    public function get_show_returns_200_and_exact_json_fragment(): void
    {
        $message = ContactMessage::factory()->create([
            'name' => 'Alice Tester',
            'email' => 'alice@example.com',
            'subject' => 'Inquiry',
        ]);

        $response = $this->getJson(route('api.demo.contact-messages.show', $message->id));

        $response->assertStatus(200);
        $response->assertHeader('X-Demo-Endpoint', 'show');
        $response->assertJsonFragment([
            'name' => 'Alice Tester',
            'email' => 'alice@example.com',
            'subject' => 'Inquiry',
        ]);
        $response->assertJsonMissing([
            'name' => 'Bob Tester',
        ]);
    }

    #[Test]
    public function get_show_returns_404_for_missing_record(): void
    {
        $response = $this->getJson(route('api.demo.contact-messages.show', 99999));

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Contact message not found.',
        ]);
    }

    #[Test]
    public function get_existing_manifest_endpoint_asserts_json_and_headers(): void
    {
        $response = $this->get('/manifest.json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'name',
            'short_name',
            'start_url',
            'display',
            'background_color',
            'theme_color',
            'icons',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST REQUEST TESTS
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function post_store_creates_record_and_returns_201(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+251911000001',
            'subject' => 'Support Request',
            'message' => 'I need help with my account.',
        ];

        $response = $this->postJson(route('api.demo.contact-messages.store'), $payload);

        $response->assertStatus(201);
        $response->assertHeader('X-Demo-Endpoint', 'create');
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Contact message created successfully.');
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'email',
                'phone',
                'subject',
                'message',
                'is_read',
                'created_at',
                'updated_at',
            ],
        ]);

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'john@example.com',
            'subject' => 'Support Request',
        ]);
    }

    #[Test]
    public function post_store_returns_422_on_validation_failure(): void
    {
        $response = $this->postJson(route('api.demo.contact-messages.store'), [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
    }

    #[Test]
    public function post_existing_contact_form_asserts_redirect_and_database(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Public Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Hello',
            'message' => 'This is a test message.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'visitor@example.com',
            'name' => 'Public Visitor',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PUT REQUEST TESTS
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function put_update_modifies_record_and_returns_200(): void
    {
        $message = ContactMessage::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'subject' => 'Original Subject',
            'message' => 'Original message body.',
            'is_read' => false,
        ]);

        $payload = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_read' => true,
        ];

        $response = $this->putJson(route('api.demo.contact-messages.update', $message->id), $payload);

        $response->assertStatus(200);
        $response->assertHeader('X-Demo-Endpoint', 'update');
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.email', 'updated@example.com');
        $response->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'is_read' => true,
        ]);
    }

    #[Test]
    public function put_update_returns_404_for_missing_record(): void
    {
        $response = $this->putJson(route('api.demo.contact-messages.update', 99999), [
            'name' => 'Ghost',
            'email' => 'ghost@example.com',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Contact message not found.',
        ]);
    }

    #[Test]
    public function put_update_returns_422_on_invalid_email(): void
    {
        $message = ContactMessage::factory()->create();

        $response = $this->putJson(route('api.demo.contact-messages.update', $message->id), [
            'email' => 'invalid-email-format',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE REQUEST TESTS
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function delete_destroy_removes_record_and_returns_200(): void
    {
        $message = ContactMessage::factory()->create();

        $response = $this->deleteJson(route('api.demo.contact-messages.destroy', $message->id));

        $response->assertStatus(200);
        $response->assertHeader('X-Demo-Endpoint', 'delete');
        $response->assertExactJson([
            'success' => true,
            'message' => 'Contact message deleted successfully.',
        ]);

        $this->assertDatabaseMissing('contact_messages', [
            'id' => $message->id,
        ]);
    }

    #[Test]
    public function delete_destroy_returns_404_for_missing_record(): void
    {
        $response = $this->deleteJson(route('api.demo.contact-messages.destroy', 99999));

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Contact message not found.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // ADVANCED JSON & HEADER ASSERTIONS
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function get_index_asserts_exact_json_shape_and_counts(): void
    {
        ContactMessage::factory()->create(['name' => 'Alpha']);
        ContactMessage::factory()->create(['name' => 'Beta']);

        $response = $this->getJson(route('api.demo.contact-messages.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('count', 2);
        $response->assertJsonFragment(['name' => 'Alpha']);
        $response->assertJsonFragment(['name' => 'Beta']);
    }

    #[Test]
    public function post_store_response_contains_all_expected_fields(): void
    {
        $response = $this->postJson(route('api.demo.contact-messages.store'), [
            'name' => 'Field Tester',
            'email' => 'fields@example.com',
            'subject' => 'Fields',
            'message' => 'Testing field presence.',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'email',
                'phone',
                'subject',
                'message',
                'is_read',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    #[Test]
    public function put_update_preserves_untouched_fields(): void
    {
        $message = ContactMessage::factory()->create([
            'name' => 'Preserve Me',
            'subject' => 'Important Subject',
            'message' => 'Important body.',
        ]);

        $response = $this->putJson(route('api.demo.contact-messages.update', $message->id), [
            'is_read' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Preserve Me');
        $response->assertJsonPath('data.subject', 'Important Subject');
        $response->assertJsonPath('data.message', 'Important body.');
        $response->assertJsonPath('data.is_read', true);
    }
}
