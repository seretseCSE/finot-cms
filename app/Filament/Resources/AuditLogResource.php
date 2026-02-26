<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-clipboard-document-list'; }

    public static function getNavigationGroup(): ?string { return 'Security'; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tier')
                    ->label('Tier')
                    ->badge()
                    ->color(fn ($record) => $record->tier_color),
                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_value')
                    ->label('Old Value')
                    ->getStateUsing(fn ($record) => Str::limit($record->old_value, 50)),
                Tables\Columns\TextColumn::make('new_value')
                    ->label('New Value')
                    ->getStateUsing(fn ($record) => Str::limit($record->new_value, 50)),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->options([
                        'security' => 'Security',
                        'financial' => 'Financial',
                    ]),
                Tables\Filters\SelectFilter::make('action_type')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('entity_type')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form(fn ($filter) => [
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Details'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => auth()->user()->role === 'superadmin'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === 'admin' || auth()->user()->role === 'superadmin';
    }
}

