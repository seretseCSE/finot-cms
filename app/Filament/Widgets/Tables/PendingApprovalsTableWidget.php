<?php

namespace App\Filament\Widgets\Tables;

use App\Models\FinancialTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PendingApprovalsTableWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FinancialTransaction::pending()
                    ->with(['recordedBy'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('ID'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'income' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('ETB'),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Requested By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->since(),
            ])
            ->paginated(false);
    }
}
