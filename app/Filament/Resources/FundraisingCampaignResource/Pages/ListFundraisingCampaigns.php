<?php

namespace App\Filament\Resources\FundraisingCampaignResource\Pages;

use App\Filament\Resources\FundraisingCampaignResource;
use Filament\Resources\Pages\ListRecords;

class ListFundraisingCampaigns extends ListRecords
{
    protected static string $resource = FundraisingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}

