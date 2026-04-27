<?php

namespace App\Filament\Pages;

use App\Services\SystemMonitoringService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SystemHealthMonitoring extends Page
{
    protected static ?string $title = 'System Health Monitoring';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-heart';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.system-health-monitoring';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->refreshData()),
        ];
    }

    public function refreshData()
    {
        Cache::forget('system_health_data');
        $this->notify('success', 'System health data refreshed');
    }

    public function getSystemHealthData(): array
    {
        return Cache::remember('system_health_data', 300, function () {
            $monitoringService = app(SystemMonitoringService::class);

            return $monitoringService->getSystemHealthMetrics();
        });
    }

    public function getHealthStatusColor(string $status): string
    {
        return match ($status) {
            'healthy' => 'success',
            'warning' => 'warning',
            'critical' => 'danger',
            default => 'gray',
        };
    }

    public function getHealthStatusIcon(string $status): string
    {
        return match ($status) {
            'healthy' => 'heroicon-o-check-circle',
            'warning' => 'heroicon-o-exclamation-triangle',
            'critical' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }
}
