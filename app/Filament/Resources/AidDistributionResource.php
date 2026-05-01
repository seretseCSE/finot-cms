<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AidDistributionResource\Pages;
use Filament\Schemas\Schema;
use App\Models\AidDistribution;
use App\Models\Beneficiary;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AidDistributionResource extends Resource
{
    protected static ?string $model = AidDistribution::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-hand-raised';
    }

    public static function getNavigationLabel(): string
    {
        return 'Aid Distributions';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Charity';
    }

    public static function getModelLabel(): string
    {
        return 'Aid Distribution';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Aid Distributions';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Distribution Details')
                    ->schema([
                        Forms\Components\Select::make('beneficiary_id')
                            ->label('Beneficiary')
                            ->options(fn () => Beneficiary::pluck('full_name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\DatePicker::make('distribution_date')
                            ->label('Distribution Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('aid_type')
                            ->label('Aid Type')
                            ->options([
                                'Cash' => 'Cash',
                                'Food' => 'Food',
                                'Clothing' => 'Clothing',
                                'Medical' => 'Medical',
                                'Education' => 'Education',
                                'Housing' => 'Housing',
                                'Other' => 'Other',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->prefix('ETB')
                            ->helperText('Leave empty for non-monetary aid'),

                        Forms\Components\Select::make('distributed_by')
                            ->label('Distributed By')
                            ->relationship('distributedBy', 'name')
                            ->default(fn () => Auth::id())
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('receipt_number')
                            ->label('Receipt Number')
                            ->maxLength(100),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('distribution_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('beneficiary.full_name')
                    ->label('Beneficiary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('aid_type')
                    ->label('Aid Type')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ETB')
                    ->sortable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt Number')
                    ->searchable(),

                Tables\Columns\TextColumn::make('distributedBy.name')
                    ->label('Distributed By')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Locked')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('aid_type')
                    ->options([
                        'Cash' => 'Cash',
                        'Food' => 'Food',
                        'Clothing' => 'Clothing',
                        'Medical' => 'Medical',
                        'Education' => 'Education',
                        'Housing' => 'Housing',
                        'Other' => 'Other',
                    ]),

                Tables\Filters\Filter::make('distribution_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until')
                            ->afterOrEqual('from'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('distribution_date', '>=', $date))
                            ->when($data['until'], fn ($query, $date) => $query->whereDate('distribution_date', '<=', $date));
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn ($record) => $record->canBeEdited()),
                Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->canBeEdited()),
                Actions\Action::make('lock')
                    ->label('Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn ($record) => ! $record->is_locked)
                    ->action(fn ($record) => $record->lock()),
                Actions\Action::make('unlock')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn ($record) => $record->is_locked && Auth::user()?->hasRole('charity_head'))
                    ->action(fn ($record) => $record->unlock()),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('distribution_date', 'desc');
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAidDistributions::route('/'),
            'create' => Pages\CreateAidDistribution::route('/create'),
            'edit' => Pages\EditAidDistribution::route('/{record}/edit'),
            'view' => Pages\ViewAidDistribution::route('/{record}'),
        ];
    }
}
