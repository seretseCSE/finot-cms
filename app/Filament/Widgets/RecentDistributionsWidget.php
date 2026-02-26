<?php

namespace App\Filament\Widgets;

use App\Models\AidDistribution;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentDistributionsWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return AidDistribution::with(['beneficiary', 'distributedBy'])
            ->latest('distribution_date')
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('beneficiary.full_name')
                ->label('Beneficiary')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('aid_type')
                ->label('Aid Type')
                ->badge()
                ->color('warning'),
            Tables\Columns\TextColumn::make('amount')
                ->label('Amount')
                ->money('ETB')
                ->sortable(),
            Tables\Columns\TextColumn::make('distributed_by.name')
                ->label('Distributed By')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('notes')
                ->label('Notes')
                ->limit(50),
        ];
    }

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'internal_relations_head', 'admin', 'superadmin']);
    }
}

