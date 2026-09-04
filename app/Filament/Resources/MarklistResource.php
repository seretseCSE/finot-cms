<?php

namespace App\Filament\Resources;

use App\Enums\MarklistStatus;
use App\Filament\Resources\MarklistResource\Pages;
use App\Models\Marklist;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MarklistResource extends BaseResource
{
    protected static ?string $model = Marklist::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 13;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationLabel(): string
    {
        return 'Marklists';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Textarea::make('remarks')->maxLength(2000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('class.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('term.name')->label('Semester')->sortable(),
                Tables\Columns\TextColumn::make('subject.name')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof MarklistStatus
                        ? ($state === MarklistStatus::Draft ? 'Saved' : ucfirst($state->value))
                        : (string) $state),
                Tables\Columns\TextColumn::make('assistedBy.name')->label('Entered by'),
            ])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarklists::route('/'),
        ];
    }
}
