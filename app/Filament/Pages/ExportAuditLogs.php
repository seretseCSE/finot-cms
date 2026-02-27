<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AuditLogsExport;

class ExportAuditLogs extends Page
{
    public ?array $data = [];

    protected static ?string $title = 'Export Audit Logs';

    protected static ?int $navigationSort = 5;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-arrow-down';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.export-audit-logs';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    public function mount(): void
    {
        $this->form->fill([
            'date_range' => 'last_30_days',
            'user_id' => null,
            'action_type' => 'all',
            'subject_type' => 'all',
            'format' => 'xlsx',
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Export Filters')
                ->description('Configure the audit log export parameters')
                ->schema([
                    Forms\Components\Select::make('date_range')
                        ->label('Date Range')
                        ->options([
                            'last_7_days' => 'Last 7 Days',
                            'last_30_days' => 'Last 30 Days',
                            'last_90_days' => 'Last 90 Days',
                            'last_6_months' => 'Last 6 Months',
                            'last_year' => 'Last Year',
                            'custom' => 'Custom Range',
                        ])
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state !== 'custom') {
                                $set('start_date', null);
                                $set('end_date', null);
                            }
                        }),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Start Date')
                                ->required(fn (callable $get) => $get('date_range') === 'custom')
                                ->visible(fn (callable $get) => $get('date_range') === 'custom')
                                ->native(false),

                            Forms\Components\DatePicker::make('end_date')
                                ->label('End Date')
                                ->required(fn (callable $get) => $get('date_range') === 'custom')
                                ->visible(fn (callable $get) => $get('date_range') === 'custom')
                                ->native(false),
                        ]),

                    Forms\Components\Select::make('user_id')
                        ->label('Filter by User')
                        ->placeholder('All Users')
                        ->options(function () {
                            return \App\Models\User::whereHas('auditLogs')
                                ->with('roles')
                                ->get()
                                ->map(function ($user) {
                                    $roleName = $user->roles->first()?->name ?? 'No Role';
                                    return [
                                        'id' => $user->id,
                                        'name' => "{$user->name} ({$roleName})",
                                    ];
                                })
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search) {
                            return \App\Models\User::where('name', 'like', "%{$search}%")
                                ->whereHas('auditLogs')
                                ->limit(50)
                                ->get()
                                ->map(function ($user) {
                                    $roleName = $user->roles->first()?->name ?? 'No Role';
                                    return [
                                        'id' => $user->id,
                                        'name' => "{$user->name} ({$roleName})",
                                    ];
                                })
                                ->pluck('name', 'id')
                                ->toArray();
                        }),

                    Forms\Components\Select::make('action_type')
                        ->label('Filter by Action Type')
                        ->options(function () {
                            return AuditLog::select('action')
                                ->distinct()
                                ->pluck('action', 'action')
                                ->mapWithKeys(fn ($action) => [$action => ucfirst($action)])
                                ->toArray();
                        })
                        ->placeholder('All Actions'),

                    Forms\Components\Select::make('subject_type')
                        ->label('Filter by Subject Type')
                        ->options(function () {
                            return AuditLog::select('subject_type')
                                ->distinct()
                                ->whereNotNull('subject_type')
                                ->pluck('subject_type', 'subject_type')
                                ->mapWithKeys(function ($type) {
                                    $className = class_basename($type);
                                    return [$type => $className];
                                })
                                ->toArray();
                        })
                        ->placeholder('All Subject Types'),

                    Forms\Components\Select::make('format')
                        ->label('Export Format')
                        ->options([
                            'xlsx' => 'Excel (.xlsx)',
                            'csv' => 'CSV (.csv)',
                            'pdf' => 'PDF (.pdf)',
                        ])
                        ->required(),
                ])
                ->columns(2),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Audit Logs')
                ->action('export')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Export Audit Logs')
                ->modalDescription('This will generate and download an audit log export based on your selected filters.')
                ->modalSubmitActionLabel('Export Logs'),

            Action::make('preview')
                ->label('Preview Records')
                ->action('preview')
                ->icon('heroicon-o-eye')
                ->color('secondary'),

            Action::make('reset')
                ->label('Reset Filters')
                ->action('resetFilters')
                ->icon('heroicon-o-arrow-path')
                ->color('gray'),
        ];
    }

    public function export(): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $data = $this->form->getState();
            
            // Get date range
            [$startDate, $endDate] = $this->getDateRange($data);
            
            // Build query
            $query = AuditLog::with(['causer', 'subject'])
                ->whereBetween('created_at', [$startDate, $endDate]);

            // Apply filters
            if (!empty($data['user_id'])) {
                $query->where('causer_id', $data['user_id']);
            }

            if (!empty($data['action_type']) && $data['action_type'] !== 'all') {
                $query->where('action', $data['action_type']);
            }

            if (!empty($data['subject_type']) && $data['subject_type'] !== 'all') {
                $query->where('subject_type', $data['subject_type']);
            }

            // Count records
            $recordCount = $query->count();
            
            if ($recordCount === 0) {
                Notification::make()
                    ->title('No Records Found')
                    ->body('No audit logs match the selected criteria.')
                    ->warning()
                    ->send();
                return redirect()->back();
            }

            // Generate filename
            $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.' . $data['format'];

            // Log the export action
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'export_audit_logs',
                    'filters' => $data,
                    'record_count' => $recordCount,
                    'format' => $data['format'],
                ])
                ->log('Exported audit logs');

            // Export based on format
            switch ($data['format']) {
                case 'xlsx':
                    return Excel::download(new AuditLogsExport($query), $filename);
                case 'csv':
                    return Excel::download(new AuditLogsExport($query), $filename, \Maatwebsite\Excel\Excel::CSV);
                case 'pdf':
                    return $this->exportToPdf($query, $filename);
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('Failed to export audit logs: ' . $e->getMessage())
                ->danger()
                ->send();

            // Log the error
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => 'export_audit_logs_failed',
                    'error' => $e->getMessage(),
                ])
                ->log('Failed to export audit logs');
        }
    }

    public function preview(): void
    {
        try {
            $data = $this->form->getState();
            
            // Get date range
            [$startDate, $endDate] = $this->getDateRange($data);
            
            // Build query
            $query = AuditLog::with(['causer', 'subject'])
                ->whereBetween('created_at', [$startDate, $endDate]);

            // Apply filters
            if (!empty($data['user_id'])) {
                $query->where('causer_id', $data['user_id']);
            }

            if (!empty($data['action_type']) && $data['action_type'] !== 'all') {
                $query->where('action', $data['action_type']);
            }

            if (!empty($data['subject_type']) && $data['subject_type'] !== 'all') {
                $query->where('subject_type', $data['subject_type']);
            }

            $recordCount = $query->count();

            Notification::make()
                ->title('Preview Results')
                ->body("Found {$recordCount} audit log records matching your criteria.")
                ->info()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Preview Failed')
                ->body('Failed to preview: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetFilters(): void
    {
        $this->mount();
        
        Notification::make()
            ->title('Filters Reset')
            ->body('All filters have been reset to default values.')
            ->success()
            ->send();
    }

    private function getDateRange(array $data): array
    {
        $now = now();
        
        switch ($data['date_range']) {
            case 'last_7_days':
                return [$now->copy()->subDays(7)->startOfDay(), $now->endOfDay()];
            case 'last_30_days':
                return [$now->copy()->subDays(30)->startOfDay(), $now->endOfDay()];
            case 'last_90_days':
                return [$now->copy()->subDays(90)->startOfDay(), $now->endOfDay()];
            case 'last_6_months':
                return [$now->copy()->subMonths(6)->startOfDay(), $now->endOfDay()];
            case 'last_year':
                return [$now->copy()->subYear()->startOfDay(), $now->endOfDay()];
            case 'custom':
                return [
                    Carbon::parse($data['start_date'])->startOfDay(),
                    Carbon::parse($data['end_date'])->endOfDay()
                ];
            default:
                return [$now->copy()->subDays(30)->startOfDay(), $now->endOfDay()];
        }
    }

    private function exportToPdf($query, string $filename): \Symfony\Component\HttpFoundation\Response
    {
        // For PDF export, you would typically use a library like DomPDF or TCPDF
        // For now, we'll create a simple text-based PDF preview
        $records = $query->limit(1000)->get(); // Limit for performance
        
        $content = '<h1>Audit Logs Export</h1>';
        $content .= '<p>Generated: ' . now()->format('Y-m-d H:i:s') . '</p>';
        $content .= '<p>Total Records: ' . $records->count() . '</p>';
        $content .= '<table border="1">';
        $content .= '<tr><th>Date</th><th>User</th><th>Action</th><th>Subject</th><th>IP Address</th></tr>';
        
        foreach ($records as $record) {
            $content .= '<tr>';
            $content .= '<td>' . $record->created_at->format('Y-m-d H:i:s') . '</td>';
            $content .= '<td>' . ($record->causer?->name ?? 'System') . '</td>';
            $content .= '<td>' . ucfirst($record->action) . '</td>';
            $content .= '<td>' . ($record->subject ? class_basename($record->subject_type) : 'N/A') . '</td>';
            $content .= '<td>' . $record->ip_address . '</td>';
            $content .= '</tr>';
        }
        
        $content .= '</table>';
        
        // This is a simplified approach - in production, use a proper PDF library
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function getStatistics(): array
    {
        $stats = [
            'total_logs' => AuditLog::count(),
            'last_24h' => AuditLog::where('created_at', '>=', now()->subDay())->count(),
            'last_7d' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
            'last_30d' => AuditLog::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        // Most active users
        $stats['top_users'] = AuditLog::with('causer')
            ->whereNotNull('causer_id')
            ->selectRaw('causer_id, COUNT(*) as count')
            ->groupBy('causer_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'name' => $log->causer?->name ?? 'Unknown',
                    'count' => $log->count,
                ];
            });

        // Most common actions
        $stats['top_actions'] = AuditLog::selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'action')
            ->toArray();

        return $stats;
    }
}
