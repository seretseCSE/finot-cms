<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LibrarySubcategoryResource\Pages;
use Filament\Schemas\Schema;
use App\Models\LibraryCategory;
use App\Models\LibrarySubcategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;

class LibrarySubcategoryResource extends BaseResource
{
    protected static ?string $model = LibrarySubcategory::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder-open';
    }

    public static function getNavigationLabel(): string
    {
        return 'Library Subcategories';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function canViewAny(): bool
    {
        if (\App\Support\RoleGate::isAny(['student', 'parent'])) {
            return false;
        }

        return parent::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->options(fn () => LibraryCategory::query()->where('status', 'Active')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

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
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => LibraryCategory::query()->orderBy('name')->pluck('name', 'id')->all()),

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
            'index' => Pages\ListLibrarySubcategories::route('/'),
            'create' => Pages\CreateLibrarySubcategory::route('/create'),
            'edit' => Pages\EditLibrarySubcategory::route('/{record}/edit'),
        ];
    }
}
