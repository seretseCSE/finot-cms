<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BatchResource\Pages;
use App\Models\Batch;
use App\Services\Academics\BatchService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BatchResource extends BaseResource
{
    protected static ?string $model = Batch::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationLabel(): string
    {
        return 'Batches';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->label('Batch name')
                ->placeholder('Class of 2026')
                ->required()
                ->maxLength(120),
            Forms\Components\TextInput::make('start_year')
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100),
            Forms\Components\Select::make('tenure_years')
                ->options([
                    4 => '4 years',
                    5 => '5 years',
                ])
                ->default(4)
                ->required()
                ->disabled(fn (?Batch $record) => $record !== null),
            Forms\Components\Select::make('status')
                ->options([
                    'open' => 'Open',
                    'closed' => 'Closed',
                ])
                ->default('open')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('start_year')->sortable(),
                Tables\Columns\TextColumn::make('tenure_years')->label('Tenure'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('years_count')->counts('years')->label('Years'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('close')
                    ->visible(fn (Batch $record) => $record->status === 'open')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function (Batch $record) {
                        app(BatchService::class)->close($record);
                        Notification::make()->title('Batch closed')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBatches::route('/'),
            'create' => Pages\CreateBatch::route('/create'),
            'edit' => Pages\EditBatch::route('/{record}/edit'),
        ];
    }
}
