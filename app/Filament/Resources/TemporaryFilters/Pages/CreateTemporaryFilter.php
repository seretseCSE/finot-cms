<?php

namespace App\Filament\Resources\TemporaryFilters\Pages;

use App\Filament\Resources\TemporaryFilters\TemporaryFilterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTemporaryFilter extends CreateRecord
{
    protected static string $resource = TemporaryFilterResource::class;
}
