<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords as FilamentListRecords;

class ListRecords extends FilamentListRecords
{
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        $this->cachedHeaderActions = collect($this->cachedHeaderActions)
            ->reject(fn ($action): bool => $action instanceof CreateAction)
            ->values()
            ->all();
    }
}
