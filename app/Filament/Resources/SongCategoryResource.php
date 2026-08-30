<?php

namespace App\Filament\Resources;


use App\Filament\Support\HidesFromNavigation;
use App\Filament\Resources\SongCategoryResource\Pages;
use Filament\Schemas\Schema;
use App\Filament\Resources\SongCategoryResource\RelationManagers;
use App\Models\SongCategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SongCategoryResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = SongCategory::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationLabel(): string
    {
        return 'Song Categories';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function canDelete($record): bool
    {
        if ($record === null) {
            return Auth::user()?->can('song_categories.delete');
        }

        return Auth::user()?->can('song_categories.delete') && $record->canBeDeleted();
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
                            ->rows(3),

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

                Tables\Columns\TextColumn::make('songs_count')
                    ->label('Songs')
                    ->counts('songs')
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
                                ->body('Cannot delete category with assigned songs. Use soft delete instead.')
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
                    ->label('New Song Category')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No song categories found')
            ->emptyStateDescription('Create your first song category to get started.')
            ->emptyStateIcon('heroicon-o-tag');
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
            'index' => Pages\ListSongCategories::route('/'),
            'create' => Pages\CreateSongCategory::route('/create'),
            'edit' => Pages\EditSongCategory::route('/{record}/edit'),
        ];
    }
}
