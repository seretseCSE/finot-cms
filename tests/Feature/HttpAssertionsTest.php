<?php

namespace Tests\Feature;

use App\Models\FundraisingCampaign;
use App\Models\MediaItem;
use App\Models\Song;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HttpAssertionsTest extends TestCase
{
    use RefreshDatabase;

    // ==================== GET ASSERTIONS ====================

    #[Test]
    public function get_blog_returns_200_with_html_content_type(): void
    {
        \App\Models\BlogPost::factory()->published()->create();

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    #[Test]
    public function get_blog_with_search_returns_filtered_results(): void
    {
        \App\Models\BlogPost::factory()->published()->create(['title' => 'Timket Guide']);
        \App\Models\BlogPost::factory()->published()->create(['title' => 'Meskel Overview']);

        $response = $this->get('/blog?search=Timket');

        $response->assertStatus(200);
        $response->assertSee('Timket Guide');
        $response->assertDontSee('Meskel Overview');
    }

    #[Test]
    public function get_songs_with_category_filter_returns_200(): void
    {
        $category = \App\Models\SongCategory::factory()->create();
        Song::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $response = $this->get('/songs?category='.$category->id);

        $response->assertStatus(200);
    }

    #[Test]
    public function get_media_returns_200(): void
    {
        MediaItem::factory()->public()->create();

        $response = $this->get('/media');

        $response->assertStatus(200);
    }

    #[Test]
    public function get_events_returns_200(): void
    {
        $response = $this->get('/events');

        $response->assertStatus(200);
    }

    #[Test]
    public function get_manifest_json_returns_correct_content_type(): void
    {
        $response = $this->get('/manifest.json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure(['name', 'short_name', 'start_url', 'display', 'icons']);
    }

    #[Test]
    public function get_service_worker_returns_javascript_content_type(): void
    {
        $response = $this->get('/service-worker.js');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript');
    }

    #[Test]
    public function get_offline_page_returns_html(): void
    {
        $response = $this->get('/offline');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    // ==================== POST ASSERTIONS ====================

    #[Test]
    public function post_contact_form_returns_redirect_and_creates_record(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Test Subject',
            'message' => 'Test message content',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'visitor@example.com',
            'subject' => 'Test Subject',
        ]);
    }

    #[Test]
    public function post_contact_form_validation_rejects_empty_name(): void
    {
        $response = $this->post('/contact', [
            'name' => '',
            'email' => 'visitor@example.com',
            'subject' => 'Test',
            'message' => 'Message',
        ]);

        $response->assertSessionHasErrors('name');
    }

    #[Test]
    public function post_tour_registration_creates_passenger(): void
    {
        $tour = Tour::factory()->create(['status' => 'Published', 'max_capacity' => 50]);

        $response = $this->post("/tours/{$tour->id}/register", [
            'full_name' => 'John Doe',
            'phone' => '912345678',
            'passenger_count' => 2,
            'passenger_names' => ['Jane Doe'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tour_passengers', [
            'tour_id' => $tour->id,
            'full_name' => 'John Doe',
            'phone' => '+251912345678',
        ]);
    }

    #[Test]
    public function post_tour_registration_validates_required_fields(): void
    {
        $tour = Tour::factory()->create(['status' => 'Published']);

        $response = $this->post("/tours/{$tour->id}/register", [
            'full_name' => '',
            'phone' => '',
            'passenger_count' => '',
        ]);

        $response->assertSessionHasErrors(['full_name', 'phone', 'passenger_count']);
    }

    // ==================== JSON API ASSERTIONS ====================

    #[Test]
    public function get_api_fundraising_returns_json(): void
    {
        FundraisingCampaign::factory()->create([
            'status' => 'Active',
            'campaign_name' => 'Church Building Fund',
        ]);

        $response = $this->getJson('/api/fundraising');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'campaigns',
            'summary' => [
                'total_raised',
                'total_target',
                'overall_progress',
                'active_campaigns',
                'completed_campaigns',
            ],
        ]);
        $response->assertJsonPath('campaigns.0.campaign_name', 'Church Building Fund');
    }

    #[Test]
    public function get_api_fundraising_json_structure(): void
    {
        FundraisingCampaign::factory()->create([
            'status' => 'Active',
            'target_amount' => 100000,
            'total_raised' => 25000,
        ]);

        $response = $this->getJson('/api/fundraising');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'campaigns' => [
                '*' => [
                    'id',
                    'campaign_name',
                    'target_amount',
                    'total_raised',
                    'progress_percentage',
                    'status',
                ],
            ],
            'summary' => [
                'total_raised',
                'total_target',
                'overall_progress',
                'active_campaigns',
                'completed_campaigns',
            ],
        ]);
        $response->assertJsonPath('summary.total_target', 100000);
    }

    #[Test]
    public function get_api_tour_lookup_phone_returns_json(): void
    {
        $tour = Tour::factory()->create();

        $response = $this->getJson("/api/tour/lookup-phone?phone=+251911000000&tour_id={$tour->id}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'found',
            'full_name',
            'member_id',
        ]);
    }
}
