<?php

namespace App\Filament\Resources\FundraisingCampaigns\Pages;

use App\Filament\Resources\FundraisingCampaigns\FundraisingCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFundraisingCampaigns extends ListRecords
{
    protected static string $resource = FundraisingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }
}
