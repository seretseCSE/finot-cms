<?php

namespace App\Filament\Pages;

use App\Models\Tour;
use App\Models\TourPassenger;
use App\Models\TourAttendanceSession;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TourReport extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-chart-bar'; }

    protected string $view = 'filament.pages.tour-report';

    public static function getNavigationGroup(): ?string { return 'Reports'; }

    public static function getNavigationSort(): ?int { return 1; }

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'date_range' => 'month',
            'status' => 'all',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('status')
                    ->label('Tour Status')
                    ->options([
                        'all' => 'All Tours',
                        'completed' => 'Completed',
                        'in_progress' => 'In Progress',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('all')
                    ->reactive(),

                Forms\Components\Select::make('date_range')
                    ->label('Date Range')
                    ->options([
                        'month' => 'Last Month',
                        'quarter' => 'Last Quarter',
                        'year' => 'Last Year',
                        'all' => 'All Time',
                    ])
                    ->default('month')
                    ->reactive(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        $filters = $this->form->getState();
        
        return $table
            ->query(
                Tour::query()
                    ->when($filters['status'] !== 'all', function ($query) use ($filters) {
                        $query->where('status', $filters['status']);
                    })
                    ->when($filters['date_range'] !== 'all', function ($query) use ($filters) {
                        $dateFilter = match($filters['date_range']) {
                            'month' => now()->subMonth(),
                            'quarter' => now()->subQuarter(),
                            'year' => now()->subYear(),
                            default => now()->subMonth(),
                        };
                        $query->where('tour_date', '>=', $dateFilter);
                    })
                    ->with(['passengers', 'attendanceSessions'])
                    ->orderBy('tour_date', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('place')
                    ->label('Tour Place')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tour_date')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->ethiopian_date),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('total_passengers')
                    ->label('Total Passengers')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->passengers->sum('passenger_count')),

                Tables\Columns\TextColumn::make('confirmed_passengers')
                    ->label('Confirmed')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->passengers->where('status', 'Confirmed')->sum('passenger_count')),

                Tables\Columns\TextColumn::make('attended_passengers')
                    ->label('Attended')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->passengers->where('status', 'Attended')->sum('passenger_count')),

                Tables\Columns\TextColumn::make('attendance_rate')
                    ->label('Attendance Rate')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $total = $record->passengers->sum('passenger_count');
                        $attended = $record->passengers->where('status', 'Attended')->sum('passenger_count');
                        return $total > 0 ? round(($attended / $total) * 100, 1) . '%' : '0%';
                    }),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Total Revenue')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => 'ETB ' . number_format($record->passengers->where('status', 'Confirmed')->sum('passenger_count') * $record->cost_per_person, 2)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'all' => 'All Tours',
                        'completed' => 'Completed',
                        'in_progress' => 'In Progress',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('all'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.tours.view', $record)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicono-document-arrow-down')
                    ->action(fn () => $this->exportToExcel()),
                
                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicono-document-arrow-down')
                    ->action(fn () => $this->exportToPdf()),
            ]);
    }

    public function getReportMetrics(): array
    {
        $filters = $this->form->getState();
        
        $query = Tour::query()
            ->when($filters['status'] !== 'all', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when($filters['date_range'] !== 'all', function ($query) use ($filters) {
                $dateFilter = match($filters['date_range']) {
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                $query->where('tour_date', '>=', $dateFilter);
            });

        $tours = $query->with(['passengers', 'attendanceSessions'])->get();

        return [
            'total_tours' => $tours->count(),
            'completed_tours' => $tours->where('status', 'Completed')->count(),
            'total_passengers' => $tours->sum(fn ($tour) => $tour->passengers->sum('passenger_count')),
            'confirmed_passengers' => $tours->sum(fn ($tour) => $tour->passengers->where('status', 'Confirmed')->sum('passenger_count')),
            'attended_passengers' => $tours->sum(fn ($tour) => $tour->passengers->where('status', 'Attended')->sum('passenger_count')),
            'total_revenue' => $tours->sum(fn ($tour) => $tour->passengers->where('status', 'Confirmed')->sum('passenger_count') * $tour->cost_per_person),
            'average_attendance_rate' => $tours->avg(function ($tour) {
                $total = $tour->passengers->sum('passenger_count');
                $attended = $tour->passengers->where('status', 'Attended')->sum('passenger_count');
                return $total > 0 ? ($attended / $total) * 100 : 0;
            }),
        ];
    }

    public function exportToExcel()
    {
        $data = $this->table->getQuery()->get();
        
        // Implementation for Excel export
        // This would use Laravel Excel package
        return response()->json([
            'data' => $data,
            'metrics' => $this->getReportMetrics(),
        ]);
    }

    public function exportToPdf()
    {
        $data = $this->table->getQuery()->get();
        
        // Implementation for PDF export
        // This would use DomPDF or similar
        return response()->json([
            'data' => $data,
            'metrics' => $this->getReportMetrics(),
        ]);
    }
}

