<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Services\VisitorAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebsiteTrafficTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['app.url' => 'https://finot.test']);
        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function av_head_can_open_website_traffic(): void
    {
        $this->actingAs($this->createAvHeadUser())
            ->get('/admin/website-traffic')
            ->assertOk()
            ->assertSee('Website Traffic')
            ->assertSee('Pageviews');
    }

    #[Test]
    public function admin_can_open_website_traffic(): void
    {
        $this->actingAs($this->createAdminUser())
            ->get('/admin/website-traffic')
            ->assertOk();
    }

    #[Test]
    public function superadmin_can_open_website_traffic(): void
    {
        $this->actingAs($this->createSuperadminUser())
            ->get('/admin/website-traffic')
            ->assertOk();
    }

    #[Test]
    public function mezmur_head_cannot_open_website_traffic(): void
    {
        $this->actingAs($this->createMezmurHeadUser())
            ->get('/admin/website-traffic')
            ->assertForbidden();
    }

    #[Test]
    public function aggregates_pageviews_channels_landing_exit_and_new_returning(): void
    {
        $this->seedTraffic();

        $data = app(VisitorAnalyticsService::class)->forDays(7);
        $overview = $data['overview'];

        $this->assertSame(6, $overview['pageviews']);
        $this->assertSame(3, $overview['unique']);
        $this->assertSame(2, $overview['new_sessions']);
        $this->assertSame(1, $overview['returning_sessions']);
        $this->assertSame(100.0, $overview['deltas']['pageviews']);

        $this->assertSame('/', $data['top_pages'][0]['path']);
        $this->assertSame(3, $data['top_pages'][0]['views']);

        $landings = collect($data['landing_pages'])->keyBy('path');
        $this->assertSame(2, $landings['/']['views']);
        $this->assertSame(1, $landings['/media']['views']);

        $exits = collect($data['exit_pages'])->keyBy('path');
        $this->assertSame(1, $exits['/news']['views']);
        $this->assertSame(1, $exits['/media/1']['views']);
        $this->assertSame(1, $exits['/']['views']);

        $channels = collect($data['channels'])->keyBy('channel');
        $this->assertSame(2, $channels['Direct']['views']);
        $this->assertSame(1, $channels['Search']['views']);
        $this->assertSame(1, $channels['Social']['views']);
        $this->assertSame(1, $channels['Referral']['views']);

        $hosts = collect($data['referrers'])->pluck('host');
        $this->assertTrue($hosts->contains('www.google.com'));
        $this->assertTrue($hosts->contains('t.me'));
        $this->assertTrue($hosts->contains('example.com'));
        $this->assertFalse($hosts->contains('finot.test'));
    }

    #[Test]
    public function classifies_channels_from_referrers(): void
    {
        $service = app(VisitorAnalyticsService::class);

        $this->assertSame('Direct', $service->channelForReferrer(null));
        $this->assertSame('Direct', $service->channelForReferrer(''));
        $this->assertSame('Search', $service->channelForReferrer('https://www.google.com/search?q=finot'));
        $this->assertSame('Social', $service->channelForReferrer('https://t.me/finot'));
        $this->assertSame('Referral', $service->channelForReferrer('https://example.com/blog'));
        $this->assertNull($service->channelForReferrer('https://finot.test/news'));
        $this->assertSame('/', $service->sectionForPath('/'));
        $this->assertSame('/news', $service->sectionForPath('/news/123'));
    }

    private function seedTraffic(): void
    {
        $now = now();
        $sessionA = hash('sha256', 'session-a');
        $sessionB = hash('sha256', 'session-b');
        $sessionC = hash('sha256', 'session-c');

        PageView::query()->create([
            'path' => '/',
            'referrer' => null,
            'session_hash' => $sessionA,
            'created_at' => $now->copy()->subDays(20),
        ]);

        PageView::query()->create([
            'path' => '/',
            'referrer' => null,
            'session_hash' => $sessionA,
            'created_at' => $now->copy()->subDays(2)->setTime(10, 0),
        ]);
        PageView::query()->create([
            'path' => '/news',
            'referrer' => 'https://finot.test/',
            'session_hash' => $sessionA,
            'created_at' => $now->copy()->subDays(2)->setTime(10, 1),
        ]);

        PageView::query()->create([
            'path' => '/media',
            'referrer' => 'https://www.google.com/search?q=choir',
            'session_hash' => $sessionB,
            'created_at' => $now->copy()->subDays(1)->setTime(11, 0),
        ]);
        PageView::query()->create([
            'path' => '/media/1',
            'referrer' => 'https://t.me/finot',
            'session_hash' => $sessionB,
            'created_at' => $now->copy()->subDays(1)->setTime(11, 1),
        ]);

        PageView::query()->create([
            'path' => '/',
            'referrer' => 'https://example.com/blog',
            'session_hash' => $sessionC,
            'created_at' => $now->copy()->subDays(1)->setTime(15, 0),
        ]);
        PageView::query()->create([
            'path' => '/',
            'referrer' => null,
            'session_hash' => $sessionC,
            'created_at' => $now->copy()->subDays(1)->setTime(15, 1),
        ]);

        PageView::query()->create([
            'path' => '/',
            'referrer' => null,
            'session_hash' => hash('sha256', 'session-old'),
            'created_at' => $now->copy()->subDays(10),
        ]);

        PageView::query()->create([
            'path' => '/about',
            'referrer' => null,
            'session_hash' => hash('sha256', 'session-old-2'),
            'created_at' => $now->copy()->subDays(11),
        ]);

        PageView::query()->create([
            'path' => '/contact',
            'referrer' => null,
            'session_hash' => hash('sha256', 'session-old-3'),
            'created_at' => $now->copy()->subDays(12),
        ]);
    }
}
