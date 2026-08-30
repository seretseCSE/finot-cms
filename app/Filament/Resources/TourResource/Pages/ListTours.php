<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Exports\TourExport;
use App\Filament\Resources\TourResource;
use App\Jobs\ProcessExportJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourResource::class;

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
                        ->options(TourExport::availableColumns())
                        ->default(array_keys(TourExport::availableColumns()))
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
                        exportClass: TourExport::class,
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
                ->visible(fn () => TourResource::canCreate()),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No tours found';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Create your first tour to get started.';
    }

    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-map';
    }
}
