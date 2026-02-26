<?php

namespace App\Filament\Pages;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinancialAuditTrailPage extends Page
{
    public static function getNavigationIcon(): ?string { return 'heroicon-o-shield-check'; }

    public static function getNavigationLabel(): string { return 'Financial Audit Trail'; }

    public static function getNavigationGroup(): ?string { return 'Finance'; }

    protected string $view = 'filament.pages.financial-audit-trail';

    public static function getNavigationSort(): ?int { return 7; }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DB::table('audit_logs')
                    ->whereIn('action', [
                        'contribution_created',
                        'contribution_updated',
                        'contribution_deleted',
                        'contribution_archived',
                        'donation_created',
                        'donation_updated',
                        'donation_deleted',
                        'financial_statement_generated',
                        'contributions_archived',
                    ])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn ($record) => match($record->action) {
                        'contribution_created' => 'success',
                        'contribution_updated' => 'warning',
                        'contribution_deleted' => 'danger',
                        'contribution_archived' => 'info',
                        'donation_created' => 'success',
                        'donation_updated' => 'warning',
                        'donation_deleted' => 'danger',
                        'financial_statement_generated' => 'primary',
                        'contributions_archived' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($record) => match($record->action) {
                        'contribution_created' => 'Contribution Created',
                        'contribution_updated' => 'Contribution Updated',
                        'contribution_deleted' => 'Contribution Deleted',
                        'contribution_archived' => 'Contribution Archived',
                        'donation_created' => 'Donation Created',
                        'donation_updated' => 'Donation Updated',
                        'donation_deleted' => 'Donation Deleted',
                        'financial_statement_generated' => 'Statement Generated',
                        'contributions_archived' => 'Contributions Archived',
                        default => $record->action,
                    }),

                Tables\Columns\TextColumn::make('performed_by')
                    ->label('User')
                    ->formatStateUsing(function ($record) {
                        if ($record->performed_by === 'system') {
                            return 'System';
                        }
                        
                        $user = DB::table('users')->find($record->performed_by);
                        return $user ? $user->name : 'Unknown';
                    }),

                Tables\Columns\TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('old_value')
                    ->label('Old Value')
                    ->limit(50)
                    ->formatStateUsing(function ($record) {
                        if (!$record->old_value) return 'N/A';
                        
                        $data = json_decode($record->old_value, true);
                        if (is_array($data)) {
                            return json_encode($data, JSON_PRETTY_PRINT);
                        }
                        return $record->old_value;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('new_value')
                    ->label('New Value')
                    ->limit(50)
                    ->formatStateUsing(function ($record) {
                        if (!$record->new_value) return 'N/A';
                        
                        $data = json_decode($record->new_value, true);
                        if (is_array($data)) {
                            return json_encode($data, JSON_PRETTY_PRINT);
                        }
                        return $record->new_value;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tier')
                    ->label('Tier')
                    ->badge()
                    ->color(fn ($record) => $record->tier == 2 ? 'danger' : 'info')
                    ->formatStateUsing(fn ($record) => 'Tier ' . $record->tier),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'contribution_created' => 'Contribution Created',
                        'contribution_updated' => 'Contribution Updated',
                        'contribution_deleted' => 'Contribution Deleted',
                        'contribution_archived' => 'Contribution Archived',
                        'donation_created' => 'Donation Created',
                        'donation_updated' => 'Donation Updated',
                        'donation_deleted' => 'Donation Deleted',
                        'financial_statement_generated' => 'Statement Generated',
                        'contributions_archived' => 'Contributions Archived',
                    ]),

                Tables\Filters\SelectFilter::make('tier')
                    ->label('Tier')
                    ->options([
                        1 => 'Tier 1 (System)',
                        2 => 'Tier 2 (Permanent)',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $data['start_date'] && $data['end_date']
                            ? $query->whereBetween('created_at', [$data['start_date'], $data['end_date']])
                            : $query;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    protected function getViewData(): array
    {
        return [
            'table' => $this->table(\Filament\Tables\Table::make()),
        ];
    }
}

