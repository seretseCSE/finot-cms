<?php

namespace App\Filament\Widgets\Tables;

use App\Models\AidDistribution;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentAidDistributionsTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AidDistribution::with(['beneficiary', 'distributedBy'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('beneficiary.full_name')
                    ->label('Beneficiary'),
                Tables\Columns\TextColumn::make('aid_type'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('ETB'),
                Tables\Columns\TextColumn::make('distribution_date')
                    ->date(),
                Tables\Columns\TextColumn::make('distributedBy.name')
                    ->label('Distributed By'),
            ])
            ->paginated(false);
    }
}
