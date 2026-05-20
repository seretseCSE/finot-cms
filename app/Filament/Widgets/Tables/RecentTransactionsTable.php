<?php

namespace App\Filament\Widgets\Tables;

use App\Models\FinancialTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentTransactionsTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FinancialTransaction::with(['recordedBy', 'approvedBy'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => $state === 'income' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('category')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('ETB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
