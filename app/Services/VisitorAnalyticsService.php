<?php

namespace App\Services;

use App\Models\PageView;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VisitorAnalyticsService
{
    public const CACHE_TTL = 300;

    public const RANGES = [7, 30, 90];

    /**
     * @var list<string>
     */
    private const SEARCH_NEEDLES = [
        'google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.',
    ];

    /**
     * @var list<string>
     */
    private const SOCIAL_NEEDLES = [
        'facebook.', 'instagram.', 't.me', 'telegram.', 'twitter.', 'x.com',
        'tiktok.', 'linkedin.', 'youtube.', 'youtu.be', 'whatsapp.',
    ];

    /**
     * @return array{
     *     days: int,
     *     overview: array<string, mixed>,
     *     trend: list<array{date: string, label: string, pageviews: int, unique: int}>,
     *     by_hour: list<array{hour: int, label: string, pageviews: int}>,
     *     by_dow: list<array{dow: int, label: string, pageviews: int}>,
     *     top_pages: list<array{path: string, views: int}>,
     *     landing_pages: list<array{path: string, views: int}>,
     *     exit_pages: list<array{path: string, views: int}>,
     *     channels: list<array{channel: string, views: int, percent: float}>,
     *     referrers: list<array{host: string, views: int}>,
     *     sections: list<array{section: string, views: int}>
     * }
     */
    public function forDays(int $days): array
    {
        $days = (int) $days;
        $days = in_array($days, self::RANGES, true) ? $days : 30;

        return Cache::remember("visitor_analytics:page:{$days}", self::CACHE_TTL, function () use ($days) {
            [$from, $to] = $this->window($days);
            [$prevFrom, $prevTo] = $this->previousWindow($from, $days);

            return [
                'days' => $days,
                'overview' => $this->overviewWithDeltas($from, $to, $prevFrom, $prevTo),
                'trend' => $this->trend($from, $to),
                'by_hour' => $this->byHour($from, $to),
                'by_dow' => $this->byDayOfWeek($from, $to),
                'top_pages' => $this->topPages($from, $to),
                'landing_pages' => $this->sessionEdgePages($from, $to, 'min'),
                'exit_pages' => $this->sessionEdgePages($from, $to, 'max'),
                'channels' => $this->channels($from, $to),
                'referrers' => $this->referrers($from, $to),
                'sections' => $this->sections($from, $to),
            ];
        });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function window(int $days): array
    {
        $to = now();
        $from = $to->copy()->subDays($days);

        return [$from, $to];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function previousWindow(Carbon $from, int $days): array
    {
        return [$from->copy()->subDays($days), $from->copy()];
    }

    /**
     * @return array{
     *     pageviews: int,
     *     unique: int,
     *     views_per_visit: float,
     *     bounce_rate: float,
     *     new_sessions: int,
     *     returning_sessions: int,
     *     deltas: array<string, float|null>
     * }
     */
    public function overviewWithDeltas(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $current = $this->overview($from, $to);
        $previous = $this->overview($prevFrom, $prevTo);

        $current['deltas'] = [
            'pageviews' => $this->delta($current['pageviews'], $previous['pageviews']),
            'unique' => $this->delta($current['unique'], $previous['unique']),
            'views_per_visit' => $this->delta($current['views_per_visit'], $previous['views_per_visit']),
            'bounce_rate' => $this->delta($current['bounce_rate'], $previous['bounce_rate']),
            'new_sessions' => $this->delta($current['new_sessions'], $previous['new_sessions']),
            'returning_sessions' => $this->delta($current['returning_sessions'], $previous['returning_sessions']),
        ];

        return $current;
    }

    /**
     * @return array{
     *     pageviews: int,
     *     unique: int,
     *     views_per_visit: float,
     *     bounce_rate: float,
     *     new_sessions: int,
     *     returning_sessions: int
     * }
     */
    public function overview(Carbon $from, Carbon $to): array
    {
        $pageviews = (int) $this->rangeQuery($from, $to)->count();
        $unique = (int) $this->rangeQuery($from, $to)
            ->selectRaw('COUNT(DISTINCT session_hash) as c')
            ->value('c');

        $bounces = (int) DB::query()
            ->fromSub(
                $this->rangeQuery($from, $to)
                    ->select('session_hash')
                    ->groupBy('session_hash')
                    ->havingRaw('COUNT(*) = 1'),
                'bounces'
            )
            ->count();

        $returning = 0;
        if ($unique > 0) {
            $inRange = $this->rangeQuery($from, $to)->select('session_hash')->distinct();
            $returning = (int) PageView::query()
                ->where('created_at', '<', $from)
                ->whereIn('session_hash', $inRange)
                ->selectRaw('COUNT(DISTINCT session_hash) as c')
                ->value('c');
        }

        $new = max(0, $unique - $returning);

        return [
            'pageviews' => $pageviews,
            'unique' => $unique,
            'views_per_visit' => $unique > 0 ? round($pageviews / $unique, 2) : 0.0,
            'bounce_rate' => $unique > 0 ? round(($bounces / $unique) * 100, 1) : 0.0,
            'new_sessions' => $new,
            'returning_sessions' => $returning,
        ];
    }

    /**
     * @return list<array{date: string, label: string, pageviews: int, unique: int}>
     */
    public function trend(Carbon $from, Carbon $to): array
    {
        $rows = $this->rangeQuery($from, $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as pageviews, COUNT(DISTINCT session_hash) as unique_visitors')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $points = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);
            $points[] = [
                'date' => $key,
                'label' => $cursor->format('M j'),
                'pageviews' => (int) ($row->pageviews ?? 0),
                'unique' => (int) ($row->unique_visitors ?? 0),
            ];
            $cursor->addDay();
        }

        return $points;
    }

    /**
     * @return list<array{hour: int, label: string, pageviews: int}>
     */
    public function byHour(Carbon $from, Carbon $to): array
    {
        $hourExpr = $this->isSqlite()
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';

        $counts = $this->rangeQuery($from, $to)
            ->selectRaw("{$hourExpr} as hour, COUNT(*) as pageviews")
            ->groupByRaw($hourExpr)
            ->pluck('pageviews', 'hour');

        $points = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $points[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'pageviews' => (int) ($counts[$hour] ?? $counts[(string) $hour] ?? 0),
            ];
        }

        return $points;
    }

    /**
     * @return list<array{dow: int, label: string, pageviews: int}>
     */
    public function byDayOfWeek(Carbon $from, Carbon $to): array
    {
        $dowExpr = $this->isSqlite()
            ? "CAST(strftime('%w', created_at) AS INTEGER)"
            : '(DAYOFWEEK(created_at) - 1)';

        $counts = $this->rangeQuery($from, $to)
            ->selectRaw("{$dowExpr} as dow, COUNT(*) as pageviews")
            ->groupByRaw($dowExpr)
            ->pluck('pageviews', 'dow');

        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $points = [];
        for ($dow = 0; $dow < 7; $dow++) {
            $points[] = [
                'dow' => $dow,
                'label' => $labels[$dow],
                'pageviews' => (int) ($counts[$dow] ?? $counts[(string) $dow] ?? 0),
            ];
        }

        return $points;
    }

    /**
     * @return list<array{path: string, views: int}>
     */
    public function topPages(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->rangeQuery($from, $to)
            ->selectRaw('path, COUNT(*) as views')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['path' => (string) $row->path, 'views' => (int) $row->views])
            ->all();
    }

    /**
     * @return list<array{path: string, views: int}>
     */
    public function sessionEdgePages(Carbon $from, Carbon $to, string $edge, int $limit = 10): array
    {
        $agg = $edge === 'max' ? 'MAX' : 'MIN';
        $fromValue = $from->toDateTimeString();
        $toValue = $to->toDateTimeString();

        $edges = DB::table('page_views')
            ->selectRaw("session_hash, {$agg}(created_at) as edge_at")
            ->where('created_at', '>=', $fromValue)
            ->where('created_at', '<', $toValue)
            ->groupBy('session_hash');

        return DB::table('page_views as p')
            ->joinSub($edges, 's', function ($join) {
                $join->on('s.session_hash', '=', 'p.session_hash')
                    ->on('s.edge_at', '=', 'p.created_at');
            })
            ->where('p.created_at', '>=', $fromValue)
            ->where('p.created_at', '<', $toValue)
            ->selectRaw('p.path as path, COUNT(*) as views')
            ->groupBy('p.path')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['path' => (string) $row->path, 'views' => (int) $row->views])
            ->all();
    }

    /**
     * @return list<array{channel: string, views: int, percent: float}>
     */
    public function channels(Carbon $from, Carbon $to): array
    {
        $counts = [
            'Direct' => 0,
            'Search' => 0,
            'Social' => 0,
            'Referral' => 0,
        ];

        $this->referrerRows($from, $to)->each(function ($row) use (&$counts) {
            $channel = $this->channelForReferrer($row->referrer);
            if ($channel === null) {
                return;
            }
            $counts[$channel] += (int) $row->views;
        });

        $total = array_sum($counts);

        return collect($counts)
            ->map(fn (int $views, string $channel) => [
                'channel' => $channel,
                'views' => $views,
                'percent' => $total > 0 ? round(($views / $total) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{host: string, views: int}>
     */
    public function referrers(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $hosts = [];

        $this->referrerRows($from, $to)->each(function ($row) use (&$hosts) {
            $host = $this->referrerHost($row->referrer);
            if ($host === null || $this->isInternalHost($host)) {
                return;
            }
            $hosts[$host] = ($hosts[$host] ?? 0) + (int) $row->views;
        });

        arsort($hosts);

        return collect($hosts)
            ->take($limit)
            ->map(fn (int $views, string $host) => ['host' => $host, 'views' => $views])
            ->values()
            ->all();
    }

    /**
     * @return list<array{section: string, views: int}>
     */
    public function sections(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $sections = [];

        $this->rangeQuery($from, $to)
            ->selectRaw('path, COUNT(*) as views')
            ->groupBy('path')
            ->get()
            ->each(function ($row) use (&$sections) {
                $section = $this->sectionForPath((string) $row->path);
                $sections[$section] = ($sections[$section] ?? 0) + (int) $row->views;
            });

        arsort($sections);

        return collect($sections)
            ->take($limit)
            ->map(fn (int $views, string $section) => ['section' => $section, 'views' => $views])
            ->values()
            ->all();
    }

    public function channelForReferrer(?string $referrer): ?string
    {
        $host = $this->referrerHost($referrer);

        if ($referrer === null || $referrer === '' || $host === null) {
            return 'Direct';
        }

        if ($this->isInternalHost($host)) {
            return null;
        }

        foreach (self::SEARCH_NEEDLES as $needle) {
            if (str_contains($host, $needle) || str_starts_with($host, rtrim($needle, '.'))) {
                return 'Search';
            }
        }

        foreach (self::SOCIAL_NEEDLES as $needle) {
            if (str_contains($host, $needle)) {
                return 'Social';
            }
        }

        return 'Referral';
    }

    public function sectionForPath(string $path): string
    {
        $trimmed = '/'.ltrim($path, '/');
        if ($trimmed === '/' || $trimmed === '') {
            return '/';
        }

        $segments = explode('/', trim($trimmed, '/'));

        return '/'.($segments[0] ?: '');
    }

    public function delta(int|float $current, int|float $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\PageView>
     */
    private function rangeQuery(Carbon $from, Carbon $to)
    {
        return PageView::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to);
    }

    /**
     * @return Collection<int, object{referrer: ?string, views: int}>
     */
    private function referrerRows(Carbon $from, Carbon $to): Collection
    {
        return $this->rangeQuery($from, $to)
            ->selectRaw('referrer, COUNT(*) as views')
            ->groupBy('referrer')
            ->get();
    }

    private function referrerHost(?string $referrer): ?string
    {
        if ($referrer === null || $referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower($host);
    }

    private function isInternalHost(string $host): bool
    {
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($appHost !== '' && ($host === $appHost || str_ends_with($host, '.'.$appHost))) {
            return true;
        }

        return in_array($host, ['localhost', '127.0.0.1'], true);
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
}
