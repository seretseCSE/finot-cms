<?php

namespace App\Filament\Resources;


use App\Filament\Support\HidesFromNavigation;
use App\Filament\Resources\SchoolClassResource\Pages;
use App\Filament\Resources\SchoolClassResource\RelationManagers\TeachersRelationManager;
use App\Models\ClassModel;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SchoolClassResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = ClassModel::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-library';
    }

    public static function getNavigationLabel(): string
    {
        return 'Classes';
    }

    public static function canDelete($record): bool
    {
        return (bool) Auth::user()?->can('class_models.delete') && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(200)
                    ->unique(ignoreRecord: true),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->maxLength(1000),

                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
                Tables\Columns\TextColumn::make('enrollments_count')
                    ->counts('enrollments')
                    ->label('Current Enrollment Count'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn (ClassModel $record) => $record->canBeDeleted()),
                Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->visible(fn (ClassModel $record) => ! $record->canBeDeleted())
                    ->requiresConfirmation()
                    ->action(fn (ClassModel $record) => $record->delete()),
                Actions\RestoreAction::make(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolClasses::route('/'),
            'create' => Pages\CreateSchoolClass::route('/create'),
            'edit' => Pages\EditSchoolClass::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            TeachersRelationManager::class,
        ];
    }
}
