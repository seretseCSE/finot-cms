<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ErrorLogResource\Pages;
use App\Models\ErrorLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ErrorLogResource extends Resource
{
    protected static ?string $model = ErrorLog::class;

    public static function getNavigationIcon(): ?string { return null; }

    public static function getNavigationGroup(): ?string { return 'System'; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('error_type')
                    ->label('Error Type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error Message')
                    ->searchable()
                    ->limit(100),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->searchable()
                    ->limit(100),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('error_type')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('url')
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
                Actions\ViewAction::make()
                    ->label('View Details'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
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
            'index' => Pages\ListErrorLogs::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === 'superadmin';
    }
}

