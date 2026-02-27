<?php

namespace App\Filament\Widgets;

use App\Services\SystemMonitoringService;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class GlobalOversightErrorLogs extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Recent Error Logs';
    }

    public function getTableRecords(): Collection
    {
        if (!Auth::user()->hasRole('superadmin')) {
            return collect([]);
        }

        $service = new SystemMonitoringService();
        return collect($service->getErrorLogs(100));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('timestamp')
                    ->label('Timestamp')
                    ->sortable()
                    ->searchable(),
                    
                BadgeColumn::make('level')
                    ->label('Level')
                    ->colors([
                        'CRITICAL' => 'danger',
                        'ERROR' => 'danger',
                        'WARNING' => 'warning',
                        'INFO' => 'info',
                    ]),
                    
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(100)
                    ->searchable(),
                    
                TextColumn::make('context')
                    ->label('Context')
                    ->limit(50)
                    ->formatStateUsing(fn ($state) => $state ? substr($state, 0, 50) . '...' : '')
                    ->searchable(),
            ])
            ->defaultSort('timestamp', 'desc')
            ->paginated([10, 25, 50])
            ->striped();
    }

    protected function getTableQuery(): Builder
    {
        // This is a static widget, so we return an empty query
        // and use getTableRecords() instead
        return parent::getTableQuery();
    }
}
