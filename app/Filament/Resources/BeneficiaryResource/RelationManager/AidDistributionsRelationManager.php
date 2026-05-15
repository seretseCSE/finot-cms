<?php

namespace App\Filament\Resources\BeneficiaryResource\RelationManager;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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
                    ->sortable()
                    ->placeholder('N/A'),
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
                    ->visible(fn ($record) => ! $record->is_locked)
                    ->action(fn ($record) => $record->lock()),
                Actions\Action::make('unlock')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn ($record) => $record->is_locked && Auth::user()?->can('aid_distributions.delete'))
                    ->action(fn ($record) => $record->unlock()),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Add Distribution'),
            ]);
    }

    public function canViewAny(): bool
    {
        $user = Auth::user();

        // Superadmin can view everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission if model supports it
        if (method_exists(static::getRelatedModel(), 'getPermissionName')) {
            $permission = static::getRelatedModel()::getPermissionName('view');
            return $user->can($permission);
        }

        // Fallback to superadmin only for models without permission system
        return false;
    }

    public function canCreate(): bool
    {
        $user = Auth::user();

        // Superadmin can create everything
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Check specific permission if model supports it
        if (method_exists(static::getRelatedModel(), 'getPermissionName')) {
            $permission = static::getRelatedModel()::getPermissionName('create');
            return $user->can($permission);
        }

        // Fallback to superadmin only for models without permission system
        return false;
    }
}
