<?php

namespace App\Filament\Resources\BeneficiaryResource\Pages;

use App\Exports\BeneficiaryExport;
use App\Filament\Resources\BeneficiaryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

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
                ->action(function () {
                    return Excel::download(new BeneficiaryExport, 'beneficiaries_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make(),
        ];
    }
}
