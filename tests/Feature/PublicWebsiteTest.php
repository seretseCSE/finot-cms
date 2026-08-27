<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_page_loads(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    #[Test]
    public function home_page_loads_in_amharic_locale(): void
    {
        $response = $this->withCookie('locale', 'am')->get('/');
        $response->assertStatus(200);
    }

    #[Test]
    public function about_page_loads(): void
    {
        $response = $this->get('/about');
        $response->assertStatus(200);
    }


    #[Test]
    public function home_page_uses_editorial_layout(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('ft-hero', false);
        $response->assertDontSee('home-legacy');
    }

    #[Test]
    public function blog_index_redirects_to_news(): void
    {
        BlogPost::factory()->create(['status' => 'Published', 'published_at' => now()]);
        $this->get('/blog')->assertRedirect(route('news', ['tab' => 'blog']));
    }

    #[Test]
    public function blog_single_page_loads(): void
    {
        $post = BlogPost::factory()->create(['status' => 'Published', 'slug' => 'test-post', 'published_at' => now()]);
        $response = $this->get("/blog/{$post->slug}");
        $response->assertStatus(200);
    }

    #[Test]
    public function contact_page_loads(): void
    {
        $response = $this->get('/contact');
        $response->assertStatus(200);
    }

    #[Test]
    public function contact_form_can_be_submitted(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Inquiry',
            'message' => 'Test message',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_messages', ['email' => 'visitor@example.com']);
    }

    #[Test]
    public function events_page_redirects_to_news(): void
    {
        $this->get('/events')->assertRedirect(route('news', ['tab' => 'events']));
    }

    #[Test]
    public function library_page_loads(): void
    {
        $response = $this->get('/library');
        $response->assertStatus(200);
    }

    #[Test]
    public function media_page_loads(): void
    {
        $response = $this->get('/media');
        $response->assertStatus(200);
    }

    #[Test]
    public function songs_page_redirects_to_media(): void
    {
        $this->get('/songs')->assertRedirect(route('media', ['tab' => 'songs']));
    }

    #[Test]
    public function tours_page_loads(): void
    {
        $response = $this->get('/tours');
        $response->assertStatus(200);
    }

    #[Test]
    public function fundraising_page_loads(): void
    {
        $response = $this->get('/fundraising');
        $response->assertStatus(200);
    }

    #[Test]
    public function language_can_be_switched(): void
    {
        $response = $this->post('/language/am');
        $response->assertRedirect();
    }

    #[Test]
    public function pwa_manifest_is_served(): void
    {
        $response = $this->get('/manifest.json');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function service_worker_is_served(): void
    {
        $response = $this->get('/service-worker.js');
        $response->assertStatus(200);
    }

    #[Test]
    public function offline_page_loads(): void
    {
        $response = $this->get('/offline');
        $response->assertStatus(200);
    }
}
