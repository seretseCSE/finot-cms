<?php

namespace App\Filament\Resources\SongResource\Pages;

use App\Filament\Resources\SongResource;
use Filament\Resources\Pages\ListRecords;

class ListSongs extends ListRecords
{
    protected static string $resource = SongResource::class;
}
