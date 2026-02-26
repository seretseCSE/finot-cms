<?php

namespace App\Filament\Resources\MemberGroupResource\Pages;

use App\Filament\Resources\MemberGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMemberGroup extends ViewRecord
{
    protected static string $resource = MemberGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}

