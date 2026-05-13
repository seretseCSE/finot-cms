<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LibraryCategoryResource\Pages;
use App\Filament\Resources\LibraryCategoryResource\RelationManagers;
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
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 9;
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
            \Filament\Schemas\Components\Section::make('Category Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Category Name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(500),

                    Forms\Components\TextInput::make('display_order')
                        ->label('Display Order')
                        ->numeric()
                        ->default(0),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'Active' => 'Active',
                            'Inactive' => 'Inactive',
                        ])
                        ->required()
                        ->default('Active'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('subcategories_count')
                    ->label('Subcategories')
                    ->counts('subcategories')
                    ->sortable(),

                Tables\Columns\TextColumn::make('resources_count')
                    ->label('Resources')
                    ->counts('resources')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status === 'Active' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn ($record) => static::canEdit($record)),

                Actions\DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record))
                    ->before(function ($record, Actions\DeleteAction $action) {
                        if ($record !== null && ! $record->canBeDeleted()) {
                            $action->halt();

                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete')
                                ->body('Cannot delete category with assigned resources. Use soft delete instead.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'Inactive']);
                            }
                        }),

                    Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'Active']);
                            }
                        }),
                ]),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('New Library Category')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No library categories found')
            ->emptyStateDescription('Create your first library category to get started.')
            ->emptyStateIcon('heroicon-o-folder')
            ->defaultSort('display_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubcategoriesRelationManager::class,
        ];
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
