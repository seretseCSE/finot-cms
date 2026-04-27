<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function blog_index_shows_only_published_posts(): void
    {
        $published = BlogPost::factory()->published()->create(['title' => 'Published Post']);
        BlogPost::factory()->draft()->create(['title' => 'Draft Post']);
        BlogPost::factory()->archived()->create(['title' => 'Archived Post']);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertSee('Published Post');
        $response->assertDontSee('Draft Post');
        $response->assertDontSee('Archived Post');
    }

    #[Test]
    public function blog_index_filters_by_search_query(): void
    {
        BlogPost::factory()->published()->create(['title' => 'Timket Celebration Guide']);
        BlogPost::factory()->published()->create(['title' => 'Meskel Festival Overview']);

        $response = $this->get('/blog?search=Timket');

        $response->assertStatus(200);
        $response->assertSee('Timket Celebration Guide');
        $response->assertDontSee('Meskel Festival Overview');
    }

    #[Test]
    public function blog_index_filters_by_tag(): void
    {
        BlogPost::factory()->published()->create([
            'title' => 'Ethiopian Orthodox Traditions',
            'tags' => 'Ethiopian Orthodox, Traditions, Faith',
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'Modern Worship Styles',
            'tags' => 'Modern, Worship, Music',
        ]);

        $response = $this->get('/blog?tag=Ethiopian+Orthodox');

        $response->assertStatus(200);
        $response->assertSee('Ethiopian Orthodox Traditions');
        $response->assertDontSee('Modern Worship Styles');
    }

    #[Test]
    public function blog_index_paginates_results(): void
    {
        BlogPost::factory()->count(15)->published()->create();

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertViewHas('posts', function ($posts) {
            return $posts->count() === 9;
        });
    }

    #[Test]
    public function blog_show_displays_post_by_slug(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'Timket Celebration Guide',
            'slug' => 'timket-celebration-guide',
            'content' => 'This is the full content about Timket.',
        ]);

        $response = $this->get('/blog/timket-celebration-guide');

        $response->assertStatus(200);
        $response->assertSee('Timket Celebration Guide');
        $response->assertSee('This is the full content about Timket.');
    }

    #[Test]
    public function blog_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->get('/blog/nonexistent-slug');

        $response->assertStatus(404);
    }

    #[Test]
    public function blog_show_includes_related_posts(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'Timket Main Post',
            'slug' => 'timket-main',
            'tags' => 'Ethiopian Orthodox, Timket',
        ]);
        $related = BlogPost::factory()->published()->create([
            'title' => 'Timket Related Post',
            'slug' => 'timket-related',
            'tags' => 'Ethiopian Orthodox, Celebration',
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'Unrelated Post',
            'slug' => 'unrelated',
            'tags' => 'Modern, Worship',
        ]);

        $response = $this->get('/blog/timket-main');

        $response->assertStatus(200);
        $response->assertViewHas('relatedPosts', function ($relatedPosts) use ($related) {
            return $relatedPosts->contains('id', $related->id);
        });
    }

    #[Test]
    public function blog_slug_auto_generates_on_create(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Timket Celebration', 'slug' => null]);

        $this->assertEquals('timket-celebration', $post->fresh()->slug);
    }

    #[Test]
    public function blog_slug_generates_unique_slug_for_duplicate_title(): void
    {
        BlogPost::factory()->create(['title' => 'Timket Celebration', 'slug' => null]);
        $post2 = BlogPost::factory()->create(['title' => 'Timket Celebration', 'slug' => null]);

        $this->assertEquals('timket-celebration-1', $post2->fresh()->slug);
    }
}
