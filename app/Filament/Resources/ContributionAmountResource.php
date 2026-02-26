<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Filament\Resources\ContributionAmountResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\ContributionAmount;
use App\Models\MemberGroup;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ContributionAmountResource extends Resource
{
    protected static ?string $model = ContributionAmount::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-currency-dollar'; }

    public static function getNavigationLabel(): string { return 'Contribution Settings'; }

    public static function getNavigationGroup(): ?string { return 'Finance'; }

    public static function getModelLabel(): string { return 'Contribution Amount'; }

    public static function getPluralModelLabel(): string { return 'Contribution Amounts'; }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin'])
               && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Contribution Details')
                    ->schema([
                        Forms\Components\Select::make('group_id')
                            ->label('Member Group')
                            ->relationship('group', 'name')
                            ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('month_name')
                            ->label('Month')
                            ->options(EthiopianDateHelper::getMonthsForContribution())
                            ->searchable()
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (Birr)')
                            ->numeric()
                            ->step(0.01)
                            ->min(0.01)
                            ->required()
                            ->prefix('Birr'),

                        EthiopianDatePicker::make('effective_from')
                            ->label('Effective From')
                            ->required()
                            ->reactive(),

                        EthiopianDatePicker::make('effective_to')
                            ->label('Effective To')
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Member Group')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('month_name')
                    ->label('Month')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ETB')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => 'Birr ' . number_format($state, 2)),

                Tables\Columns\TextColumn::make('effective_from')
                    ->label('Effective From')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_to')
                    ->label('Effective To')
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? $state->format('M d, Y') : 'Ongoing'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->isCurrentlyActive() ? 'success' : 'gray')
                    ->formatStateUsing(fn ($record) => $record->status),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Member Group')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('month_name')
                    ->label('Month')
                    ->options(EthiopianDateHelper::getMonthsForContribution()),

                Tables\Filters\TernaryFilter::make('active_only')
                    ->label('Active Only')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Historical')
                    ->queries(
                        true: fn (Builder $query) => $query->active(),
                        false: fn (Builder $query) => $query->whereNotActive(),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (ContributionAmount $record) => static::canEdit($record)),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (ContributionAmount $record) => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Contribution Amount')
                    ->modalDescription('Are you sure you want to delete this contribution amount? This action cannot be undone.'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => static::canDelete(null))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Selected Contribution Amounts')
                    ->modalDescription('Are you sure you want to delete the selected contribution amounts? This action cannot be undone.'),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContributionAmounts::route('/'),
            'create' => Pages\CreateContributionAmount::route('/create'),
            'edit' => Pages\EditContributionAmount::route('/{record}/edit'),
        ];
    }

    public static function beforeSave(array $data): array
    {
        // Validate no overlapping periods for same group and month
        $validation = ContributionAmount::validateNoOverlap(
            $data['group_id'],
            $data['month_name'],
            $data['effective_from'],
            $data['effective_to'] ?? null,
            request()->route('record')?->parameter('record') // Exclude current record on edit
        );

        if (!$validation['valid']) {
            Notification::make()
                ->title('Validation Error')
                ->body($validation['message'])
                ->danger()
                ->send();

            throw new \Illuminate\Validation\ValidationException([
                'overlap_error' => $validation['message']
            ]);
        }

        return $data;
    }
}

