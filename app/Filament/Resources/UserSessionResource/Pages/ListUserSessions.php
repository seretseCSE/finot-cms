<?php

namespace App\Filament\Resources\UserSessionResource\Pages;

use App\Filament\Resources\UserSessionResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListUserSessions extends ListRecords
{
    protected static string $resource = UserSessionResource::class;

    protected ?string $heading = 'Active User Sessions';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshTable')
                ->color('secondary'),
        ];
    }

    public function refreshTable(): void
    {
        // Simply redirect to refresh the page and reload data
        $this->redirect($this->getUrl(), navigate: true);
    }
}
