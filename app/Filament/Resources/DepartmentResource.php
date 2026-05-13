<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use Filament\Schemas\Schema;
use App\Models\Department;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-office';
    }

    public static function getNavigationLabel(): string
    {
        return 'Departments';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Department Information')
                    ->schema([
                        Forms\Components\TextInput::make('name_en')
                            ->label('Name (English)')
                            ->required()
                            ->maxLength(191),

                        Forms\Components\TextInput::make('name_am')
                            ->label('Name (Amharic)')
                            ->maxLength(191),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                    ])
                    ->columns(2),

                Section::make('Leadership')
                    ->schema([
                        Forms\Components\Select::make('head_user_id')
                            ->label('Department Head')
                            ->relationship('headUser', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['headUser']))
            ->columns([
                Tables\Columns\TextColumn::make('name_en')
                    ->label('Name (EN)')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name_am')
                    ->label('Name (AM)')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),


                Tables\Columns\TextColumn::make('headUser.name')
                    ->label('Department Head')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No Head Assigned'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->default(true),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Staff Count')
                    ->counts('users')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('members_count')
                    ->label('Members')
                    ->counts('members')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record)),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->visible(fn () => Auth::user()?->hasRole('superadmin')),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('New Department')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No departments found')
            ->emptyStateDescription('Create your first department to get started.')
            ->emptyStateIcon('heroicon-o-building-office');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
