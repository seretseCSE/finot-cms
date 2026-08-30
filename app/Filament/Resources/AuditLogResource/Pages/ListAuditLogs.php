<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Exports\AuditLogsExport;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\Pages\ListRecords;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ExportAuditService;
use App\Support\RoleGate;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => RoleGate::can('audit_logs.export')
                    || RoleGate::can('page.system.audit-logs-export'))
                ->modalHeading('Export Audit Logs')
                ->modalDescription('Choose filters, then download the matching records.')
                ->modalSubmitActionLabel('Export')
                ->form([
                    Select::make('date_range')
                        ->label('Date Range')
                        ->options([
                            'last_7_days' => 'Last 7 Days',
                            'last_30_days' => 'Last 30 Days',
                            'last_90_days' => 'Last 90 Days',
                            'last_6_months' => 'Last 6 Months',
                            'last_year' => 'Last Year',
                            'custom' => 'Custom Range',
                        ])
                        ->default('last_30_days')
                        ->required()
                        ->live(),
                    Grid::make(2)
                        ->schema([
                            DatePicker::make('start_date')
                                ->label('Start Date')
                                ->native(false)
                                ->required(fn (callable $get): bool => $get('date_range') === 'custom')
                                ->visible(fn (callable $get): bool => $get('date_range') === 'custom'),
                            DatePicker::make('end_date')
                                ->label('End Date')
                                ->native(false)
                                ->afterOrEqual('start_date')
                                ->required(fn (callable $get): bool => $get('date_range') === 'custom')
                                ->visible(fn (callable $get): bool => $get('date_range') === 'custom'),
                        ]),
                    Select::make('user_id')
                        ->label('User')
                        ->placeholder('All Users')
                        ->searchable()
                        ->options(function () {
                            return User::query()
                                ->whereHas('auditLogs')
                                ->with('roles')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(function (User $user): array {
                                    $role = $user->roles->first()?->name ?? 'No Role';

                                    return [$user->id => "{$user->name} ({$role})"];
                                })
                                ->all();
                        }),
                    Select::make('action_type')
                        ->label('Action Type')
                        ->placeholder('All Actions')
                        ->options(fn () => AuditLog::query()
                            ->whereNotNull('action_type')
                            ->distinct()
                            ->orderBy('action_type')
                            ->pluck('action_type', 'action_type')
                            ->mapWithKeys(fn ($action) => [$action => ucfirst((string) $action)])
                            ->all()),
                    Select::make('entity_type')
                        ->label('Entity Type')
                        ->placeholder('All Entity Types')
                        ->options(fn () => AuditLog::query()
                            ->whereNotNull('entity_type')
                            ->distinct()
                            ->orderBy('entity_type')
                            ->pluck('entity_type', 'entity_type')
                            ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                            ->all()),
                    Select::make('format')
                        ->label('Format')
                        ->options([
                            'xlsx' => 'Excel (.xlsx)',
                            'csv' => 'CSV (.csv)',
                        ])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $query = $this->exportQuery($data);
                    $recordCount = $query->count();

                    if ($recordCount === 0) {
                        Notification::make()
                            ->title('No Records Found')
                            ->body('No audit logs match the selected filters.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $format = $data['format'] ?? 'xlsx';
                    $filename = 'audit_logs_'.now()->format('Y-m-d_His').'.'.$format;

                    ExportAuditService::log(
                        resourceType: 'audit_logs',
                        format: $format,
                        recordCount: $recordCount,
                        filters: $data,
                    );

                    if ($format === 'csv') {
                        return Excel::download(new AuditLogsExport($query), $filename, \Maatwebsite\Excel\Excel::CSV);
                    }

                    return Excel::download(new AuditLogsExport($query), $filename);
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function exportQuery(array $data): Builder
    {
        [$startDate, $endDate] = $this->dateRange($data);

        $query = AuditLog::query()
            ->with('user')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        if (! empty($data['action_type'])) {
            $query->where('action_type', $data['action_type']);
        }

        if (! empty($data['entity_type'])) {
            $query->where('entity_type', $data['entity_type']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function dateRange(array $data): array
    {
        $now = now();

        return match ($data['date_range'] ?? 'last_30_days') {
            'last_7_days' => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(90)->startOfDay(), $now->copy()->endOfDay()],
            'last_6_months' => [$now->copy()->subMonths(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_year' => [$now->copy()->subYear()->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                Carbon::parse($data['start_date'])->startOfDay(),
                Carbon::parse($data['end_date'])->endOfDay(),
            ],
            default => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
        };
    }
}
