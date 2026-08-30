<?php

namespace App\Filament\Resources\FinancialTransactionResource\Pages;

use App\Exports\FinancialTransactionExport;
use App\Filament\Resources\FinancialTransactionResource;
use App\Jobs\ProcessExportJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\ListRecords;

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
                ->form([
                    CheckboxList::make('columns')
                        ->label('Columns')
                        ->options(FinancialTransactionExport::availableColumns())
                        ->default(array_keys(FinancialTransactionExport::availableColumns()))
                        ->columns(2)
                        ->required(),
                    Radio::make('format')
                        ->label('Format')
                        ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (array $data) {
                    ProcessExportJob::dispatch(
                        exportClass: FinancialTransactionExport::class,
                        columns: $data['columns'],
                        format: $data['format'],
                        userId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your export is being processed. You will be notified when it is ready.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
