<?php

namespace App\Filament\Resources\TemporaryFilters;

use App\Filament\Resources\TemporaryFilters\Pages\CreateTemporaryFilter;
use Filament\Schemas\Schema;
use App\Filament\Resources\TemporaryFilters\Pages\EditTemporaryFilter;
use App\Filament\Resources\TemporaryFilters\Pages\ListTemporaryFilters;
use App\Models\TemporaryFilter;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TemporaryFilterResource extends BaseResource
{
    protected static ?string $model = TemporaryFilter::class;

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
        return 'heroicon-o-funnel';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports & Exports';
    }

    public static function getNavigationLabel(): string
    {
        return 'Temporary Filters';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

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

                DateTimePicker::make('expires_at')
                    ->label('Expires At')
                    ->helperText('Leave blank for no expiration.'),

                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                if (! Auth::user()?->hasRole(['admin', 'superadmin'])) {
                    $query->where('user_id', Auth::id());
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('resource_type')
                    ->badge(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Created By')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemporaryFilters::route('/'),
            'create' => CreateTemporaryFilter::route('/create'),
            'edit' => EditTemporaryFilter::route('/{record}/edit'),
        ];
    }
}
