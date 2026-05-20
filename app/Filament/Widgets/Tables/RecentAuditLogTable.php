<?php

namespace App\Filament\Widgets\Tables;

use App\Models\AuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentAuditLogTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AuditLog::with(['user'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User'),
                Tables\Columns\TextColumn::make('event')
                    ->badge(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Resource')
                    ->formatStateUsing(fn ($state) => class_basename($state)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since(),
            ])
            ->paginated(false);
    }
}
