<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FinancialAuditTrailPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationLabel(): string
    {
        return 'Financial Audit Trail';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Finance';
    }

    protected string $view = 'filament.pages.financial-audit-trail';

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->can('page.financial.audit-trail');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AuditLog::query()
                    ->where('tier', 'financial')
                    ->whereIn('action_type', [
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
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->badge()
                    ->color(fn ($record) => match ($record->action_type) {
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
                    ->formatStateUsing(fn ($record) => match ($record->action_type) {
                        'contribution_created' => 'Contribution Created',
                        'contribution_updated' => 'Contribution Updated',
                        'contribution_deleted' => 'Contribution Deleted',
                        'contribution_archived' => 'Contribution Archived',
                        'donation_created' => 'Donation Created',
                        'donation_updated' => 'Donation Updated',
                        'donation_deleted' => 'Donation Deleted',
                        'financial_statement_generated' => 'Statement Generated',
                        'contributions_archived' => 'Contributions Archived',
                        default => $record->action_type,
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->searchable(),

                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity Type')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('old_value')
                    ->label('Old Value')
                    ->limit(50)
                    ->formatStateUsing(function ($record) {
                        if (blank($record->old_value)) {
                            return 'N/A';
                        }

                        if (is_array($record->old_value)) {
                            return json_encode($record->old_value, JSON_PRETTY_PRINT);
                        }

                        return (string) $record->old_value;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('new_value')
                    ->label('New Value')
                    ->limit(50)
                    ->formatStateUsing(function ($record) {
                        if (blank($record->new_value)) {
                            return 'N/A';
                        }

                        if (is_array($record->new_value)) {
                            return json_encode($record->new_value, JSON_PRETTY_PRINT);
                        }

                        return (string) $record->new_value;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tier')
                    ->label('Tier')
                    ->badge()
                    ->color(fn ($record) => $record->tier === 'financial' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action_type')
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

                Tables\Filters\SelectFilter::make('entity_type')
                    ->label('Entity Type')
                    ->options([
                        'Contribution' => 'Contribution',
                        'Donation' => 'Donation',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->afterOrEqual('start_date'),
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
}
