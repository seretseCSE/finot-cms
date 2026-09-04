<?php

namespace App\Filament\Pages;

use App\Services\VisitorAnalyticsService;
use Filament\Pages\Page;

class WebsiteTraffic extends Page
{
    protected static ?string $title = 'Website Traffic';

    protected static ?string $slug = 'website-traffic';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'Website Traffic';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public function getView(): string
    {
        return 'filament.pages.website-traffic';
    }

    public static function canAccess(): bool
    {
        return \App\Support\RoleGate::can('analytics.visitors.view')
            || \App\Support\RoleGate::isAny(['admin', 'superadmin', 'av_head']);
    }

    public int $days = 30;

    public function mount(): void
    {
        $this->days = 30;
    }

    public function updatedDays(mixed $value): void
    {
        $this->days = (int) $value;

        if (! in_array($this->days, VisitorAnalyticsService::RANGES, true)) {
            $this->days = 30;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportData(): array
    {
        return app(VisitorAnalyticsService::class)->forDays($this->days);
    }
}
