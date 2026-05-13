<?php

namespace App\Filament\Resources\PredefinedReports;

use App\Filament\Resources\PredefinedReports\Pages\CreatePredefinedReport;
use Filament\Schemas\Schema;
use App\Filament\Resources\PredefinedReports\Pages\EditPredefinedReport;
use App\Filament\Resources\PredefinedReports\Pages\ListPredefinedReports;
use App\Models\PredefinedReport;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;

class PredefinedReportResource extends BaseResource
{
    protected static ?string $model = PredefinedReport::class;

    private static function canAdminAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('superadmin') || $user->hasRole('admin'));
    }

    public static function canViewAny(): bool
    {
        return self::canAdminAccess();
    }

    public static function canCreate(): bool
    {
        return self::canAdminAccess();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canDeleteAny(): bool
    {
        return self::canAdminAccess();
    }

    public static function canForceDeleteAny(): bool
    {
        return self::canAdminAccess();
    }

    public static function canReorder(): bool
    {
        return self::canAdminAccess();
    }

    public static function canReplicate(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canRestoreAny(): bool
    {
        return self::canAdminAccess();
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-chart-bar';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports & Exports';
    }

    public static function getNavigationLabel(): string
    {
        return 'Predefined Reports';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(500),

                Select::make('resource_type')
                    ->required()
                    ->options([
                        'members' => 'Members',
                        'contributions' => 'Contributions',
                        'attendance' => 'Attendance',
                        'donations' => 'Donations',
                        'financial_transactions' => 'Financial Transactions',
                        'inventory_items' => 'Inventory Items',
                        'events' => 'Events',
                    ]),

                Textarea::make('filter_criteria')
                    ->label('Filter Criteria (JSON)')
                    ->required()
                    ->formatStateUsing(fn (?array $state): string => $state ? json_encode($state, JSON_PRETTY_PRINT) : '{}')
                    ->mutateDehydratedStateUsing(function ($state): array {
                        if (is_string($state)) {
                            return json_decode($state, true) ?? [];
                        }

                        return $state ?? [];
                    })
                    ->rows(5)
                    ->helperText('Enter filter criteria as JSON. Example: {"status": "Active"}'),

                Textarea::make('columns')
                    ->label('Columns (JSON)')
                    ->formatStateUsing(fn (?array $state): string => $state ? json_encode($state, JSON_PRETTY_PRINT) : '[]')
                    ->mutateDehydratedStateUsing(function ($state): ?array {
                        if (is_string($state)) {
                            return json_decode($state, true) ?? [];
                        }

                        return $state ?? [];
                    })
                    ->rows(3)
                    ->helperText('Leave empty to select all columns. Enter as JSON array.'),

                Select::make('format')
                    ->required()
                    ->options([
                        'screen' => 'On Screen',
                        'excel' => 'Excel',
                        'pdf' => 'PDF',
                        'csv' => 'CSV',
                    ])
                    ->default('screen'),

                TextInput::make('display_order')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('resource_type')
                    ->badge(),

                Tables\Columns\TextColumn::make('format')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'screen' => 'primary',
                        'excel' => 'success',
                        'pdf' => 'danger',
                        'csv' => 'warning',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('display_order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('resource_type')
                    ->options([
                        'members' => 'Members',
                        'contributions' => 'Contributions',
                        'attendance' => 'Attendance',
                        'donations' => 'Donations',
                        'financial_transactions' => 'Financial Transactions',
                        'inventory_items' => 'Inventory Items',
                        'events' => 'Events',
                    ]),

                Tables\Filters\SelectFilter::make('format')
                    ->options([
                        'screen' => 'On Screen',
                        'excel' => 'Excel',
                        'pdf' => 'PDF',
                        'csv' => 'CSV',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ])
            ->reorderable('display_order')
            ->defaultSort('display_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPredefinedReports::route('/'),
            'create' => CreatePredefinedReport::route('/create'),
            'edit' => EditPredefinedReport::route('/{record}/edit'),
        ];
    }
}
