<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubjectResource\Pages;
use App\Models\Subject;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-book-open'; }

    public static function getNavigationLabel(): string { return 'Subjects'; }

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
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
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
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Subject $record) => $record->delete()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjects::route('/'),
            'create' => Pages\CreateSubject::route('/create'),
            'edit' => Pages\EditSubject::route('/{record}/edit'),
        ];
    }
}

