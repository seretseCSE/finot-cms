<?php

namespace App\Filament\Resources\BeneficiaryResource\RelationManager;

use App\Models\AidDistribution;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AidDistributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'aidDistributions';

    protected static ?string $title = 'Aid Distributions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('distribution_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('aid_type')
                    ->label('Aid Type')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ETB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt Number'),
                Tables\Columns\TextColumn::make('distributed_by.name')
                    ->label('Distributed By'),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Locked')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('aid_type')
                    ->options([
                        'Cash' => 'Cash',
                        'Food' => 'Food',
                        'Clothing' => 'Clothing',
                        'Medical' => 'Medical',
                        'Education' => 'Education',
                        'Housing' => 'Housing',
                        'Other' => 'Other',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn ($record) => $record->canBeEdited()),
                Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->canBeEdited()),
                Actions\Action::make('lock')
                    ->label('Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->is_locked)
                    ->action(fn ($record) => $record->lock()),
                Actions\Action::make('unlock')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn ($record) => $record->is_locked && auth()->user()->role === 'charity_head')
                    ->action(fn ($record) => $record->unlock()),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Add Distribution'),
            ]);
    }

    public function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'admin', 'superadmin']);
    }

    public function canCreate(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'admin', 'superadmin']);
    }
}
