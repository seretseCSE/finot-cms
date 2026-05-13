<?php

namespace App\Filament\Resources\FundraisingCampaigns\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Forms\Components;
use Illuminate\Support\Facades\Auth;

class DonationsRelationManager extends RelationManager
{
    protected static string $relationship = 'donations';

    protected static ?string $recordTitleAttribute = 'donor_name';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Components\TextInput::make('donor_name')
                ->label('Donor Name')
                ->placeholder('Anonymous')
                ->maxLength(255),

            Components\TextInput::make('amount')
                ->label('Amount')
                ->required()
                ->numeric()
                ->prefix('ETB')
                ->minValue(0)
                ->step(0.01),

            Components\DatePicker::make('donation_date')
                ->label('Donation Date')
                ->required()
                ->default(now()),

            Components\Select::make('donation_type')
                ->label('Donation Type')
                ->options([
                    'Tithes' => 'Tithes',
                    'Offerings' => 'Offerings',
                    'Charity' => 'Charity',
                    'Missionary' => 'Missionary',
                    'Building' => 'Building',
                    'Other' => 'Other',
                ])
                ->required(),

            Components\TextInput::make('custom_donation_type')
                ->label('Custom Type')
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('donation_type') === 'Other')
                ->maxLength(100),

            Components\Textarea::make('notes')
                ->label('Notes')
                ->rows(2)
                ->maxLength(500),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('donor_name')
                    ->label('Donor')
                    ->placeholder('Anonymous')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ETB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('donation_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('donation_type')
                    ->label('Type')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('donation_type')
                    ->label('Type')
                    ->options([
                        'Tithes' => 'Tithes',
                        'Offerings' => 'Offerings',
                        'Charity' => 'Charity',
                        'Missionary' => 'Missionary',
                        'Building' => 'Building',
                        'Other' => 'Other',
                    ]),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['recorded_by'] = Auth::id();

                        return $data;
                    }),
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
            ->defaultSort('donation_date', 'desc');
    }
}
