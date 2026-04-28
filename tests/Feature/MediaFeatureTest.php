<?php

namespace Tests\Feature;

use App\Models\MediaCategory;
use App\Models\MediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function media_index_shows_public_media_items(): void
    {
        MediaItem::factory()->public()->create(['title' => 'Public Photo']);
        MediaItem::factory()->hidden()->create(['title' => 'Hidden Photo']);

        $response = $this->get('/media');

        $response->assertStatus(200);
        $response->assertSee('Public Photo');
        $response->assertDontSee('Hidden Photo');
    }

    #[Test]
    public function media_index_filters_by_photo_type(): void
    {
        MediaItem::factory()->public()->photo()->create(['title' => 'Photo Item']);
        MediaItem::factory()->public()->video()->create(['title' => 'Video Item']);

        $response = $this->get('/media?type=Photo');

        $response->assertStatus(200);
        $response->assertSee('Photo Item');
        $response->assertDontSee('Video Item');
    }

    #[Test]
    public function media_index_filters_by_video_type(): void
    {
        MediaItem::factory()->public()->photo()->create(['title' => 'Photo Item']);
        MediaItem::factory()->public()->video()->create(['title' => 'Video Item']);

        $response = $this->get('/media?type=Video');

        $response->assertStatus(200);
        $response->assertSee('Video Item');
        $response->assertDontSee('Photo Item');
    }

    #[Test]
    public function media_index_filters_by_category(): void
    {
        $category = MediaCategory::factory()->create();
        MediaItem::factory()->public()->create(['title' => 'Category Item', 'category_id' => $category->id]);
        MediaItem::factory()->public()->create(['title' => 'Other Item']);

        $response = $this->get('/media?category='.$category->id);

        $response->assertStatus(200);
        $response->assertSee('Category Item');
        $response->assertDontSee('Other Item');
    }

    #[Test]
    public function media_index_filters_by_search_query(): void
    {
        MediaItem::factory()->public()->create(['title' => 'Lalibela Church Photo']);
        MediaItem::factory()->public()->create(['title' => 'Addis Ababa Event']);

        $response = $this->get('/media?search=Lalibela');

        $response->assertStatus(200);
        $response->assertSee('Lalibela Church Photo');
        $response->assertDontSee('Addis Ababa Event');
    }

    #[Test]
    public function media_index_paginates_results(): void
    {
        MediaItem::factory()->count(18)->public()->create();

        $response = $this->get('/media');

        $response->assertStatus(200);
        $response->assertViewHas('mediaGroups', function ($groups) {
            return $groups->count() <= 12;
        });
    }
}
