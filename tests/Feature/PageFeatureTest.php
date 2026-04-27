<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function about_page_renders_fallback_when_no_page_exists(): void
    {
        $response = $this->get(route('about'));
        $response->assertStatus(200);
        $response->assertSee('About Us');
        $response->assertSee('Our Mission');
    }

    #[Test]
    public function about_page_renders_cms_content_when_published(): void
    {
        Page::factory()->published()->create([
            'slug' => 'about',
            'title' => 'Custom About Title',
            'content' => '<p>Custom about content from CMS.</p>',
        ]);

        $response = $this->get(route('about'));
        $response->assertStatus(200);
        $response->assertSee('Custom About Title');
        $response->assertSee('Custom about content from CMS.', false);
    }

    #[Test]
    public function about_page_falls_back_when_page_is_draft(): void
    {
        Page::factory()->draft()->create([
            'slug' => 'about',
            'title' => 'Draft About',
            'content' => '<p>Draft content.</p>',
        ]);

        $response = $this->get(route('about'));
        $response->assertStatus(200);
        $response->assertSee('About Us');
        $response->assertDontSee('Draft content', false);
    }


    #[Test]
    public function about_page_shows_amharic_content_when_available(): void
    {
        Page::factory()->published()->create([
            'slug' => 'about',
            'title' => 'About Us',
            'content' => '<p>English content.</p>',
            'title_am' => 'ስለ እኛ',
            'content_am' => '<p>የአማርኛ ይዘት.</p>',
        ]);

        $response = $this->get(route('about'));
        $response->assertStatus(200);
        $response->assertSee('ስለ እኛ');
        $response->assertSee('የአማርኛ ይዘት.', false);
    }

    #[Test]
    public function page_model_generates_unique_slug(): void
    {
        $page1 = Page::factory()->create(['title' => 'Test Page']);
        $page2 = Page::factory()->create(['title' => 'Test Page']);

        $this->assertEquals('test-page', $page1->slug);
        $this->assertEquals('test-page-1', $page2->slug);
    }

    #[Test]
    public function page_model_status_color_attribute(): void
    {
        $draft = Page::factory()->draft()->make();
        $published = Page::factory()->published()->make();
        $archived = Page::factory()->archived()->make();

        $this->assertEquals('gray', $draft->status_color);
        $this->assertEquals('green', $published->status_color);
        $this->assertEquals('red', $archived->status_color);
    }
}
