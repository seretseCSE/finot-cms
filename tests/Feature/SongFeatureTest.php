<?php

namespace Tests\Feature;

use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SongFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function songs_index_redirects_to_media(): void
    {
        $this->get('/songs')->assertRedirect(route('media', ['tab' => 'songs']));
    }

    #[Test]
    public function media_songs_tab_shows_active_songs(): void
    {
        Song::factory()->create(['title' => 'Active Song', 'is_active' => true]);
        Song::factory()->create(['title' => 'Inactive Song', 'is_active' => false]);

        $response = $this->get('/media?tab=songs');

        $response->assertStatus(200);
        $response->assertSee('Active Song');
        $response->assertDontSee('Inactive Song');
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
