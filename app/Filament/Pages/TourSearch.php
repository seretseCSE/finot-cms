<?php

namespace App\Filament\Pages;

use App\Models\Tour;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TourSearch extends Page
{
    protected static ?string $title = 'Tour Search';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-magnifying-glass';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tours';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public function getView(): string
    {
        return 'filament.pages.tour-search';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'admin', 'superadmin']);
    }

    public ?string $query = '';

    public function getResults(): array
    {
        if (empty($this->query)) {
            return [];
        }

        return Tour::query()
            ->where('place', 'like', "%{$this->query}%")
            ->orWhere('description', 'like', "%{$this->query}%")
            ->limit(20)
            ->get()
            ->toArray();
    }
}
