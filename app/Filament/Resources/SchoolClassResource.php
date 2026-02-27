<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolClassResource\Pages;
use App\Filament\Resources\SchoolClassResource\RelationManagers\TeachersRelationManager;
use App\Models\ClassModel;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables; 
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SchoolClassResource extends Resource
{
    protected static ?string $model = ClassModel::class;

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-building-library'; }

    public static function getNavigationLabel(): string { return 'Classes'; }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']) && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Active')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->colors([
                        'success' => true,
                        'gray' => false,
                    ]),
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

