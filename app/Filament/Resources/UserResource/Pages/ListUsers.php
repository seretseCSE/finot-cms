<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Exports\UserExport;
use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new UserExport, 'users_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make()
                ->label('New User')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
