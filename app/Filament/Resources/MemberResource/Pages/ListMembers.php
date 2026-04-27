<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Exports\MemberExport;
use App\Filament\Resources\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(new MemberExport, 'members_' . now()->format('Y-m-d_His') . '.xlsx');
                }),
            CreateAction::make()
                ->label('New Member')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
