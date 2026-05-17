<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\SongCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SongFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function songs_index_shows_active_songs(): void
    {
        Song::factory()->create(['title' => 'Active Song', 'is_active' => true]);
        Song::factory()->create(['title' => 'Inactive Song', 'is_active' => false]);

        $response = $this->get('/songs');

        $response->assertStatus(200);
        $response->assertSee('Active Song');
        $response->assertDontSee('Inactive Song');
    }

    #[Test]
    public function songs_index_filters_by_category(): void
    {
        $category = SongCategory::factory()->create();
        Song::factory()->create(['title' => 'Category Song', 'category_id' => $category->id, 'is_active' => true]);
        Song::factory()->create(['title' => 'Other Song', 'is_active' => true]);

        $response = $this->get('/songs?category='.$category->id);

        $response->assertStatus(200);
        $response->assertSee('Category Song');
        $response->assertDontSee('Other Song');
    }

    #[Test]
    public function songs_index_filters_by_audio_only(): void
    {
        Song::factory()->create(['title' => 'Audio Song', 'audio_file' => 'audio.mp3', 'video_url' => null, 'is_active' => true]);
        Song::factory()->create(['title' => 'Video Song', 'audio_file' => null, 'video_url' => 'https://www.youtube.com/watch?v=test123', 'is_active' => true]);

        $response = $this->get('/songs?has_audio=1');

        $response->assertStatus(200);
        $response->assertSee('Audio Song');
        $response->assertDontSee('Video Song');
    }

    #[Test]
    public function songs_index_filters_by_video_only(): void
    {
        Song::factory()->create(['title' => 'Audio Only', 'audio_file' => 'audio.mp3', 'video_url' => null, 'is_active' => true]);
        Song::factory()->create(['title' => 'Video Song', 'audio_file' => null, 'video_url' => 'https://www.youtube.com/watch?v=test456', 'is_active' => true]);

        $response = $this->get('/songs?has_video=1');

        $response->assertStatus(200);
        $response->assertSee('Video Song');
        $response->assertDontSee('Audio Only');
    }

    #[Test]
    public function songs_index_filters_by_search_query(): void
    {
        Song::factory()->create(['title' => 'Timket Mezmur', 'is_active' => true]);
        Song::factory()->create(['title' => 'Fasika Praise', 'is_active' => true]);

        $response = $this->get('/songs?search=Timket');

        $response->assertStatus(200);
        $response->assertSee('Timket Mezmur');
        $response->assertDontSee('Fasika Praise');
    }

    #[Test]
    public function songs_index_paginates_results(): void
    {
        Song::factory()->count(20)->create(['is_active' => true]);

        $response = $this->get('/songs');

        $response->assertStatus(200);
        $response->assertViewHas('songs', function ($songs) {
            return $songs->count() <= 15;
        });
    }

    #[Test]
    public function songs_show_displays_individual_song(): void
    {
        $song = Song::factory()->create([
            'title' => 'Display Song',
            'lyrics' => 'These are the song lyrics.',
            'is_active' => true,
        ]);

        $response = $this->get('/songs/'.$song->id);

        $response->assertStatus(200);
        $response->assertSee('Display Song');
        $response->assertSee('These are the song lyrics.');
    }
}
