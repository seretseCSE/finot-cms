<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Pages\WebsiteTraffic;
use App\Filament\Support\ClickableStat;
use App\Services\VisitorAnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

class VisitorStatsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Website traffic (7 days)';

    protected int | array | null $columns = 3;

    public static function canView(): bool
    {
        return \App\Support\RoleGate::can('analytics.visitors.view')
            || \App\Support\RoleGate::isAny(['admin', 'superadmin', 'av_head']);
    }

    protected function getStats(): array
    {
        $overview = app(VisitorAnalyticsService::class)->forDays(7)['overview'];
        $url = $this->trafficUrl();

        return [
            $this->kpi('Pageviews', number_format($overview['pageviews']), $overview['deltas']['pageviews'], 'heroicon-o-eye', $url),
            $this->kpi('Unique visitors', number_format($overview['unique']), $overview['deltas']['unique'], 'heroicon-o-users', $url),
            $this->kpi('Views / visit', (string) $overview['views_per_visit'], $overview['deltas']['views_per_visit'], 'heroicon-o-arrows-right-left', $url),
            $this->kpi('Bounce rate', $overview['bounce_rate'].'%', $overview['deltas']['bounce_rate'], 'heroicon-o-arrow-uturn-left', $url, invert: true),
            $this->kpi('New sessions', number_format($overview['new_sessions']), $overview['deltas']['new_sessions'], 'heroicon-o-sparkles', $url),
            $this->kpi('Returning', number_format($overview['returning_sessions']), $overview['deltas']['returning_sessions'], 'heroicon-o-arrow-path', $url),
        ];
    }

    private function kpi(string $label, string $value, ?float $delta, string $icon, ?string $url, bool $invert = false): Stat
    {
        $delta = $delta ?? 0.0;
        $up = $delta >= 0;
        $positive = $invert ? ! $up : $up;

        $stat = ClickableStat::make($label, $value, $url)
            ->description(sprintf('%s%s%% vs previous 7 days', $up ? '+' : '', $delta))
            ->descriptionIcon($up ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->icon($icon)
            ->color($positive ? 'success' : 'danger');

        return $stat;
    }

    private function trafficUrl(): ?string
    {
        try {
            return WebsiteTraffic::getUrl();
        } catch (Throwable) {
            return ClickableStat::pageUrl(WebsiteTraffic::class);
        }
    }
}
