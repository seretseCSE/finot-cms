<?php

namespace App\Filament\Resources\UserSessionResource\Pages;

use App\Filament\Resources\UserSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserSessions extends ListRecords
{
    protected static string $resource = UserSessionResource::class;

    protected ?string $heading = 'Active User Sessions';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshTable')
                ->color('secondary'),
        ];
    }

    public function refreshTable(): void
    {
        $this->table->resetFilters();
        $this->table->resetSearch();
        $this->table->resetPage();
    }
}

