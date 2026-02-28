<?php

namespace App\Filament\Resources\MemberGroupResource\Pages;

use App\Filament\Resources\MemberGroupResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListMemberGroups extends ListRecords
{
    protected static string $resource = MemberGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

