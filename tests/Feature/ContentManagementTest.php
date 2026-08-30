<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function av_head_can_access_blog_posts_page(): void
    {
        $user = $this->createAvHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/blog-posts');
        $response->assertStatus(200);
    }

    #[Test]
    public function av_head_can_access_blog_posts_create_page(): void
    {
        $user = $this->createAvHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/blog-posts/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function av_head_can_access_announcements_page(): void
    {
        $user = $this->createAvHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/announcements');
        $response->assertStatus(200);
    }

    #[Test]
    public function av_head_can_access_media_page(): void
    {
        $user = $this->createAvHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/media');
        $response->assertStatus(200);
    }

    #[Test]
    public function worship_monitor_can_access_songs_page(): void
    {
        $user = $this->createWorshipMonitorUser();
        $this->actingAs($user);

        $response = $this->get('/admin/songs');
        $response->assertStatus(200);
    }

    #[Test]
    public function worship_monitor_can_access_rehearsals_page(): void
    {
        $user = $this->createWorshipMonitorUser();
        $this->actingAs($user);

        $response = $this->get('/admin/rehearsals');
        $response->assertStatus(200);
    }

    #[Test]
    public function faq_resource_pages_are_accessible(): void
    {
        $user = $this->createAvHeadUser();
        $this->actingAs($user);

        $this->get('/admin/f-a-q-s')->assertStatus(200);
        $this->get('/admin/f-a-q-s/create')->assertStatus(200);
    }

    #[Test]
    public function public_blog_page_exists(): void
    {
        $this->get('/blog')->assertOk();
    }

    #[Test]
    public function public_media_page_exists(): void
    {
        $response = $this->get('/media');
        $response->assertStatus(200);
    }

    #[Test]
    public function public_songs_redirects_to_media(): void
    {
        $this->get('/songs')->assertRedirect(route('media', ['tab' => 'songs']));
    }

    #[Test]
    public function song_categories_resource_accessible(): void
    {
        $user = $this->createWorshipMonitorUser();
        $this->actingAs($user);

        $response = $this->get('/admin/song-categories');
        $response->assertStatus(200);
    }

    #[Test]
    public function rehearsals_resource_accessible(): void
    {
        $user = $this->createWorshipMonitorUser();
        $this->actingAs($user);

        $response = $this->get('/admin/rehearsals');
        $response->assertStatus(200);
    }

    #[Test]
    public function songs_resource_accessible(): void
    {
        $user = $this->createWorshipMonitorUser();
        $this->actingAs($user);

        $response = $this->get('/admin/songs');
        $response->assertStatus(200);
    }
}
