<?php

namespace App\Filament\Pages;

use App\Services\SystemMonitoringService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class SystemHealthMonitoring extends Page
{
    protected static ?string $title = 'System Health';

    protected static ?int $navigationSort = 1;

    public function getSubheading(): ?string
    {
        return 'Storage, database, errors, and sessions at a glance.';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-heart';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings & Logs';
    }

    public function getView(): string
    {
        return 'filament.pages.system-health-monitoring';
    }

    public static function canAccess(): bool
    {
        return \App\Support\RoleGate::can('page.system.health');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->refreshData()),
        ];
    }

    public function refreshData()
    {
        Cache::forget('system_health_data');
        Cache::forget('system_health_metrics');

        Notification::make()
            ->title('Health data refreshed')
            ->success()
            ->send();
    }

    public function getSystemHealthData(): array
    {
        $data = Cache::remember('system_health_data', 300, function () {
            $monitoringService = app(SystemMonitoringService::class);

            return $monitoringService->getSystemHealthMetrics();
        });

        return $this->normalizeHealthData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeHealthData(array $data): array
    {
        if (is_array($data['error_rate'] ?? null)) {
            $data['error_rate'] = $data['error_rate']['rate'] ?? 0;
        }

        if (is_array($data['uptime'] ?? null)) {
            $data['uptime'] = $data['uptime']['formatted'] ?? 'Unknown';
        }

        if (is_array($data['memory_usage'] ?? null)) {
            $memory = $data['memory_usage'];
            $data['memory_usage'] = isset($memory['percentage'])
                ? $memory['percentage'].'% ('.($memory['used'] ?? '?').' / '.($memory['total'] ?? '?').')'
                : 'Unknown';
        }

        if (is_array($data['cpu_usage'] ?? null)) {
            $data['cpu_usage'] = round((float) ($data['cpu_usage']['load_1min'] ?? 0) * 100, 2).'%';
        }

        return $data;
    }
}
