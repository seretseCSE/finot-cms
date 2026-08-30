<?php

namespace App\Filament\Pages;

use App\Models\Tour;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TourReport extends Page
{
    protected static ?string $title = 'Tour Report';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-map';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tour Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public function getView(): string
    {
        return 'filament.pages.tour-report';
    }

    public static function canAccess(): bool
    {
        return \App\Support\RoleGate::can('page.report.tour');
    }

    public ?string $status = 'all';

    public ?string $date_range = 'all';

    public function mount(): void
    {
        $this->status = 'all';
        $this->date_range = 'all';
    }

    public function getReportData(): array
    {
        $query = Tour::query()
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->date_range !== 'all', function ($query) {
                $dateFilter = match ($this->date_range) {
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                $query->where('tour_date', '>=', $dateFilter);
            })
            ->with(['passengers'])
            ->orderBy('tour_date', 'desc');

        $tours = $query->get();

        return [
            'tours' => $tours,
            'totalTours' => $tours->count(),
            'totalPassengers' => $tours->sum(fn ($t) => $t->passengers->count()),
            'totalConfirmed' => $tours->sum(fn ($t) => $t->passengers->where('status', 'Confirmed')->count()),
            'byStatus' => $tours->groupBy('status')
                ->map(fn ($group) => $group->count())
                ->toArray(),
        ];
    }
}
