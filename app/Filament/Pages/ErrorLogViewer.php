<?php

namespace App\Filament\Pages;

use App\Models\ErrorLog;
use App\Services\SystemMonitoringService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Collection;

class ErrorLogViewer extends Page
{
    protected static ?string $title = 'Error Log Viewer';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.error-log-viewer';

    public string $source = 'recorded';

    public string $level = '';

    public int $tablePage = 1;

    public int $perPage = 25;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'error-log-viewer';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bug-ant';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings & Logs';
    }

    public function getSubheading(): ?string
    {
        return 'Recorded request errors and recent lines from the Laravel log.';
    }

    public static function canAccess(): bool
    {
        return \App\Support\RoleGate::can('error_logs.view')
            || \App\Support\RoleGate::can('system.error_logs');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $this->tablePage = 1;
                    Notification::make()->title('Logs refreshed')->success()->send();
                }),
        ];
    }

    public function updatedSource(): void
    {
        $this->tablePage = 1;
        $this->level = '';
    }

    public function updatedLevel(): void
    {
        $this->tablePage = 1;
    }

    public function previousPage(): void
    {
        $this->gotoPage($this->tablePage - 1);
    }

    public function nextPage(): void
    {
        $this->gotoPage($this->tablePage + 1);
    }

    public function gotoPage(int $page): void
    {
        $this->tablePage = max(1, min($page, $this->lastPage()));
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total() / max(1, $this->perPage)));
    }

    public function total(): int
    {
        return $this->source === 'laravel'
            ? $this->laravelRows()->count()
            : $this->recordedQuery()->count();
    }

    /**
     * @return Collection<int, array{timestamp: string, level: string, message: string, context: string}>
     */
    public function rows(): Collection
    {
        if ($this->source === 'laravel') {
            return $this->laravelRows()
                ->forPage($this->tablePage, $this->perPage)
                ->values();
        }

        return $this->recordedQuery()
            ->forPage($this->tablePage, $this->perPage)
            ->get()
            ->map(fn (ErrorLog $log) => [
                'timestamp' => optional($log->created_at)->format('Y-m-d H:i:s') ?? '—',
                'level' => $log->error_type ?: 'ERROR',
                'message' => $log->error_message ?: '—',
                'context' => trim(($log->http_method ? $log->http_method.' ' : '').($log->url ?? '')),
            ]);
    }

    protected function recordedQuery()
    {
        $query = ErrorLog::query()->latest();

        if ($this->level !== '') {
            $query->where('error_type', $this->level);
        }

        return $query;
    }

    /**
     * @return Collection<int, array{timestamp: string, level: string, message: string, context: string}>
     */
    protected function laravelRows(): Collection
    {
        $rows = collect(app(SystemMonitoringService::class)->getErrorLogs(250));

        if ($this->level !== '') {
            $rows = $rows->filter(
                fn (array $log) => strcasecmp((string) ($log['level'] ?? ''), $this->level) === 0
            )->values();
        }

        return $rows;
    }
}
