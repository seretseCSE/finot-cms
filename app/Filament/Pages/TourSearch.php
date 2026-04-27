<?php

namespace App\Filament\Pages;

use App\Models\Tour;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Auth;

class TourSearch extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.tour-search';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-magnifying-glass';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tour Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationLabel(): string
    {
        return 'Tour Search';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'tour_manager', 'revenue_head', 'admin', 'superadmin']);
    }

    public ?array $filters = [];

    public bool $hasSearched = false;

    public array $results = [];

    public function mount(): void
    {
        $this->form->fill([
            'query' => '',
            'passenger_name' => '',
            'status' => 'all',
            'date_from' => null,
            'date_to' => null,
            'cost_min' => null,
            'cost_max' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                TextInput::make('query')
                    ->label('Search')
                    ->placeholder('Tour place or description...')
                    ->columnSpan(2),

                TextInput::make('passenger_name')
                    ->label('Passenger Name')
                    ->placeholder('Search by passenger or member name...'),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'all' => 'All Statuses',
                        'Draft' => 'Draft',
                        'Published' => 'Published',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->default('all'),

                DatePicker::make('date_from')
                    ->label('Tour Date From')
                    ->native(false),

                DatePicker::make('date_to')
                    ->label('Tour Date To')
                    ->native(false),

                TextInput::make('cost_min')
                    ->label('Min Cost (Birr)')
                    ->numeric()
                    ->step(0.01),

                TextInput::make('cost_max')
                    ->label('Max Cost (Birr)')
                    ->numeric()
                    ->step(0.01),
            ])
            ->columns(4);
    }

    public function searchTours(): void
    {
        $this->filters = $this->form->getState();
        $this->hasSearched = true;
        $this->results = [];

        $query = Tour::query()
            ->with(['passengers.member', 'createdBy'])
            ->when(filled($this->filters['query'] ?? null), function ($q) {
                $search = trim((string) $this->filters['query']);
                $q->where(function ($sq) use ($search) {
                    $sq->where('place', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(filled($this->filters['passenger_name'] ?? null), function ($q) {
                $name = trim((string) $this->filters['passenger_name']);
                $q->whereHas('passengers', function ($sq) use ($name) {
                    $sq->where('full_name', 'like', "%{$name}%")
                        ->orWhereHas('member', function ($mq) use ($name) {
                            $mq->where('first_name', 'like', "%{$name}%")
                                ->orWhere('father_name', 'like', "%{$name}%");
                        });
                });
            })
            ->when(($this->filters['status'] ?? 'all') !== 'all', function ($q) {
                $q->where('status', $this->filters['status']);
            })
            ->when(filled($this->filters['date_from'] ?? null), function ($q) {
                $q->whereDate('tour_date', '>=', $this->filters['date_from']);
            })
            ->when(filled($this->filters['date_to'] ?? null), function ($q) {
                $q->whereDate('tour_date', '<=', $this->filters['date_to']);
            })
            ->when(filled($this->filters['cost_min'] ?? null), function ($q) {
                $q->where('cost_per_person', '>=', $this->filters['cost_min']);
            })
            ->when(filled($this->filters['cost_max'] ?? null), function ($q) {
                $q->where('cost_per_person', '<=', $this->filters['cost_max']);
            })
            ->orderByDesc('tour_date')
            ->limit(100)
            ->get();

        $this->results = $query->map(fn (Tour $tour) => [
            'id' => $tour->id,
            'place' => $tour->place,
            'description' => $tour->description,
            'tour_date' => $tour->tour_date?->toDateString(),
            'ethiopian_date' => $tour->ethiopian_date,
            'start_time' => $tour->start_time,
            'cost' => $tour->formatted_cost,
            'status' => $tour->status,
            'status_color' => $tour->status_color,
            'max_capacity' => $tour->max_capacity,
            'confirmed_count' => $tour->confirmedPassengers->sum('passenger_count'),
            'created_by' => $tour->createdBy?->name,
        ])->toArray();
    }

    public function getTotalResultsCount(): int
    {
        return count($this->results);
    }
}
