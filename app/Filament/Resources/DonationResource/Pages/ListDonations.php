<?php

namespace App\Filament\Resources\DonationResource\Pages;

use App\Exports\DonationExport;
use App\Filament\Resources\DonationResource;
use App\Jobs\ProcessExportJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;

class ListDonations extends ListRecords
{
    protected static string $resource = DonationResource::class;

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
                        ->options(DonationExport::availableColumns())
                        ->default(array_keys(DonationExport::availableColumns()))
                        ->columns(2)
                        ->required(),
                    Radio::make('format')
                        ->label('Format')
                        ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $timestamp = now()->format('Y-m-d_His');
                        $filename = "donations_{$timestamp}.{$data['format']}";

                        ProcessExportJob::dispatch(
                            exportClass: DonationExport::class,
                            columns: $data['columns'],
                            format: $data['format'],
                            userId: auth()->id(),
                            filename: $filename,
                        );

                        $url = route('exports.download', ['filename' => $filename]);

                        Notification::make()
                            ->title('Export Ready')
                            ->body('Your donation export is ready for download.')
                            ->success()
                            ->actions([
                                NotificationAction::make('download')
                                    ->label('Download')
                                    ->url($url, shouldOpenInNewTab: true),
                            ])
                            ->send();

                        Log::info('Donation export dispatched', ['filename' => $filename]);
                    } catch (\Exception $e) {
                        Log::error('Donation export failed', ['error' => $e->getMessage()]);

                        Notification::make()
                            ->title('Export Failed')
                            ->body('An error occurred while generating the export: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make()
                ->visible(fn () => DonationResource::canCreate()),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No donations recorded';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Start recording donations to track charitable contributions to the church.';
    }

    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-gift';
    }
}
