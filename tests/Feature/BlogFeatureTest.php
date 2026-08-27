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
    public function blog_index_redirects_to_news(): void
    {
        $this->get('/blog')->assertRedirect(route('news', ['tab' => 'blog']));
    }

    #[Test]
    public function news_blog_tab_shows_only_published_posts(): void
    {
        BlogPost::factory()->published()->create(['title' => 'Published Post']);
        BlogPost::factory()->draft()->create(['title' => 'Draft Post']);
        BlogPost::factory()->archived()->create(['title' => 'Archived Post']);

        $response = $this->get('/news?tab=blog');

        $response->assertStatus(200);
        $response->assertSee('Published Post');
        $response->assertDontSee('Draft Post');
        $response->assertDontSee('Archived Post');
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

    #[Test]
    public function blog_show_displays_comments_section(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'Post With Comments',
            'slug' => 'post-with-comments',
        ]);

        $response = $this->get('/blog/post-with-comments');

        $response->assertStatus(200);
        $response->assertSee('Comments');
        $response->assertSee('Post Comment');
    }

    #[Test]
    public function guest_can_post_comment_on_blog(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'Commentable Post',
            'slug' => 'commentable-post',
        ]);

        $response = $this->post('/blog/commentable-post/comment', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'content' => 'This is a great post!',
        ]);

        $response->assertRedirect('/blog/commentable-post#comments');
        $this->assertDatabaseHas('blog_comments', [
            'blog_post_id' => $post->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'content' => 'This is a great post!',
            'is_approved' => true,
        ]);
    }

    #[Test]
    public function blog_comment_requires_name_email_and_content(): void
    {
        $post = BlogPost::factory()->published()->create([
            'slug' => 'validation-post',
        ]);

        $response = $this->post('/blog/validation-post/comment', []);

        $response->assertSessionHasErrors(['name', 'email', 'content']);
    }

    #[Test]
    public function blog_show_displays_existing_comments(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'Post With Existing Comments',
            'slug' => 'existing-comments',
        ]);

        $post->comments()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'content' => 'Amazing article!',
            'is_approved' => true,
        ]);

        $response = $this->get('/blog/existing-comments');

        $response->assertStatus(200);
        $response->assertSee('Jane Doe');
        $response->assertSee('Amazing article!');
    }
}
