<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ErrorLogViewer extends Page
{
    protected static ?string $title = 'Error Logs';

    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bug-ant';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.error-log-viewer';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearOld')
                ->label('Clear Old Logs (2+ months)')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->clearOldLogs()),

            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->refreshData()),
        ];
    }

    public function clearOldLogs()
    {
        $deleted = DB::table('error_logs')
            ->where('created_at', '<', now()->subMonths(2))
            ->delete();

        $this->notify('success', "Deleted {$deleted} old error log entries");
    }

    public function refreshData()
    {
        $this->notify('success', 'Error logs refreshed');
    }

    public function getErrorLogs(): array
    {
        return DB::table('error_logs')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'level' => $this->normalizeErrorLevel($log->error_type),
                    'message' => $log->error_message,
                    'exception' => $log->stack_trace,
                    'file' => null,
                    'line' => null,
                    'url' => $log->url,
                    'method' => $log->http_method,
                    'ip_address' => null,
                    'user_agent' => $log->user_agent,
                    'user_id' => $log->user_id,
                    'created_at' => Carbon::parse($log->created_at),
                ];
            })
            ->toArray();
    }

    protected function normalizeErrorLevel(?string $errorType): string
    {
        if (! $errorType) {
            return 'error';
        }

        $type = strtolower($errorType);

        return match (true) {
            str_contains($type, 'critical') => 'critical',
            str_contains($type, 'emergency') => 'emergency',
            str_contains($type, 'alert') => 'alert',
            str_contains($type, 'error') => 'error',
            str_contains($type, 'warning') => 'warning',
            str_contains($type, 'notice') => 'notice',
            str_contains($type, 'info') => 'info',
            str_contains($type, 'debug') => 'debug',
            default => 'error',
        };
    }

    public function getErrorLevelColor(string $level): string
    {
        return match ($level) {
            'emergency', 'alert' => 'danger',
            'critical' => 'danger',
            'error' => 'danger',
            'warning' => 'warning',
            'notice' => 'info',
            'info' => 'info',
            'debug' => 'gray',
            default => 'gray',
        };
    }

    public function getRecentErrorStats(): array
    {
        $last24h = DB::table('error_logs')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $lastWeek = DB::table('error_logs')
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        $criticalErrors = DB::table('error_logs')
            ->where(function ($query) {
                $query->where('error_type', 'like', '%critical%')
                    ->orWhere('error_type', 'like', '%emergency%')
                    ->orWhere('error_type', 'like', '%alert%')
                    ->orWhere('error_type', 'like', '%error%');
            })
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return [
            'last_24h' => $last24h,
            'last_week' => $lastWeek,
            'critical_24h' => $criticalErrors,
        ];
    }
}
