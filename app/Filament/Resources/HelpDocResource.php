<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HelpDocResource\Pages;
use Filament\Schemas\Schema;
use App\Models\HelpDoc;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class HelpDocResource extends Resource
{
    protected static ?string $model = HelpDoc::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-question-mark-circle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Help Documentation';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
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
        return Auth::user()?->hasRole(['admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Help Document')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->helperText('Unique identifier for this help doc (e.g., members.create)'),

                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\RichEditor::make('content')
                            ->label('Content')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options([
                                'general' => 'General',
                                'members' => 'Members',
                                'finance' => 'Finance',
                                'inventory' => 'Inventory',
                                'education' => 'Education',
                                'tours' => 'Tours',
                                'media' => 'Media',
                                'settings' => 'Settings',
                            ])
                            ->default('general')
                            ->required(),

                        Forms\Components\TextInput::make('context_route')
                            ->label('Context Route')
                            ->maxLength(255)
                            ->placeholder('filament.admin.resources.members.create')
                            ->helperText('The Filament route this help doc appears on. Leave empty for global.'),

                        Forms\Components\TextInput::make('display_order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->required(),

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
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'general' => 'gray',
                        'members' => 'primary',
                        'finance' => 'success',
                        'inventory' => 'warning',
                        'education' => 'info',
                        'tours' => 'danger',
                        'media' => 'secondary',
                        'settings' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('context_route')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Global'),

                Tables\Columns\TextColumn::make('display_order')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'general' => 'General',
                        'members' => 'Members',
                        'finance' => 'Finance',
                        'inventory' => 'Inventory',
                        'education' => 'Education',
                        'tours' => 'Tours',
                        'media' => 'Media',
                        'settings' => 'Settings',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No help docs found')
            ->emptyStateDescription('Create your first help document to get started.')
            ->emptyStateIcon('heroicon-o-question-mark-circle');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHelpDocs::route('/'),
            'create' => Pages\CreateHelpDoc::route('/create'),
            'edit' => Pages\EditHelpDoc::route('/{record}/edit'),
        ];
    }
}
