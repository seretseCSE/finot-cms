<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LibraryCategoryResource\Pages;
use Filament\Schemas\Schema;
use App\Models\LibraryCategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class LibraryCategoryResource extends Resource
{
    protected static ?string $model = LibraryCategory::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder';
    }

    public static function getNavigationLabel(): string
    {
        return 'Library Categories';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }

    public static function getNavigationSort(): ?int
    {
        return 11;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->maxLength(500),

                Forms\Components\TextInput::make('display_order')
                    ->label('Display Order')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                    ])
                    ->default('Active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'Active',
                        'danger' => 'Inactive',
                    ]),

                Tables\Columns\TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subcategories_count')
                    ->label('Subcategories')
                    ->state(fn ($record) => $record->subcategories()->count()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLibraryCategories::route('/'),
            'create' => Pages\CreateLibraryCategory::route('/create'),
            'edit' => Pages\EditLibraryCategory::route('/{record}/edit'),
        ];
    }
}
