<?php

namespace App\Filament\Resources\BeneficiaryResource\Pages;

use App\Exports\BeneficiaryExport;
use App\Filament\Resources\BeneficiaryResource;
use App\Jobs\ProcessExportJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\ListRecords;

class ListBeneficiaries extends ListRecords
{
    protected static string $resource = BeneficiaryResource::class;

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
                        ->options(BeneficiaryExport::availableColumns())
                        ->default(array_keys(BeneficiaryExport::availableColumns()))
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
                        exportClass: BeneficiaryExport::class,
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
            CreateAction::make()
                ->visible(fn () => BeneficiaryResource::canCreate()),
        ];
    }
}
