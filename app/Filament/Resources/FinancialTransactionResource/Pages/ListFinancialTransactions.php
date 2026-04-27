<?php

namespace App\Filament\Resources\FinancialTransactionResource\Pages;

use App\Exports\FinancialTransactionExport;
use App\Filament\Resources\FinancialTransactionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListFinancialTransactions extends ListRecords
{
    protected static string $resource = FinancialTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new FinancialTransactionExport, 'financial_transactions_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make(),
        ];
    }
}
