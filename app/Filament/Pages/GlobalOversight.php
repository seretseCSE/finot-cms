<?php

namespace App\Filament\Pages;

use App\Services\SystemMonitoringService;
use Filament\Pages\Page;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class GlobalOversight extends Page
{
    protected static ?string $title = 'Global Oversight';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-globe-alt';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.global-oversight';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_data')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $service = new SystemMonitoringService();
                    $service->clearCache();
                    $this->notify('success', 'Dashboard data refreshed successfully!');
                }),

            Action::make('export_report')
                ->label('Export Report')
                ->icon('heroicon-o-document-arrow-down')
                ->form([
                    Forms\Components\Select::make('report_type')
                        ->label('Report Type')
                        ->options([
                            'system_health' => 'System Health Report',
                            'user_activity' => 'User Activity Report',
                            'error_summary' => 'Error Summary Report',
                            'complete' => 'Complete Oversight Report',
                        ])
                        ->required()
                        ->default('complete'),
                    
                    Forms\Components\Select::make('format')
                        ->label('Format')
                        ->options([
                            'pdf' => 'PDF',
                            'excel' => 'Excel',
                            'csv' => 'CSV',
                        ])
                        ->required()
                        ->default('pdf'),
                ])
                ->action(function (array $data) {
                    // Implementation for report generation
                    $this->notify('info', "Generating {$data['format']} report: {$data['report_type']}");
                }),
        ];
    }

    public function getSystemHealthStats(): array
    {
        $service = new SystemMonitoringService();
        return $service->getSystemHealthMetrics();
    }

    public function getSystemOverviewStats(): array
    {
        $service = new SystemMonitoringService();
        return $service->getSystemOverviewStats();
    }

    public function getErrorLogs(): array
    {
        $service = new SystemMonitoringService();
        return $service->getErrorLogs(100);
    }

    public function getChartData(): array
    {
        $service = new SystemMonitoringService();
        return $service->getChartData();
    }
}
