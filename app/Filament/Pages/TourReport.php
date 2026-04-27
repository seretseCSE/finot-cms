<?php

namespace App\Filament\Pages;

use App\Exports\TourReportExport;
use App\Models\Tour;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class TourReport extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    protected string $view = 'filament.pages.tour-report';

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public ?array $filters = [];

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'tour_manager', 'revenue_head', 'finance_head', 'admin', 'superadmin']);
    }

    public function mount(): void
    {
        // Auto-generate report with default filters
        $this->filters = [
            'date_range' => 'quarter',
            'status' => 'all',
        ];
    }

    public function table(Table $table): Table
    {
        $filters = $this->filters;

        return $table
            ->query(
                Tour::query()
                    ->when($filters['status'] !== 'all', function ($query) use ($filters) {
                        $query->where('status', $filters['status']);
                    })
                    ->when($filters['date_range'] !== 'all', function ($query) use ($filters) {
                        $dateFilter = match ($filters['date_range']) {
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

                        return $total > 0 ? round(($attended / $total) * 100, 1).'%' : '0%';
                    }),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Total Revenue')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => 'ETB '.number_format($record->passengers->where('status', 'Confirmed')->sum('passenger_count') * $record->cost_per_person, 2)),
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
                Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.tours.edit', $record)),
            ])
            ->headerActions([
                Actions\Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => $this->exportToExcel()),

                Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => $this->exportToPdf()),
            ]);
    }

    public function getReportMetrics(): array
    {
        $filters = $this->filters;

        $query = Tour::query()
            ->when($filters['status'] !== 'all', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when($filters['date_range'] !== 'all', function ($query) use ($filters) {
                $dateFilter = match ($filters['date_range']) {
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
        $filters = $this->filters;
        $metrics = $this->getReportMetrics();
        $filename = 'tour-report-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new TourReportExport($filters, $metrics), $filename);
    }

    public function exportToPdf()
    {
        $filters = $this->filters;
        $metrics = $this->getReportMetrics();

        $tours = Tour::query()
            ->when($filters['status'] !== 'all', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when($filters['date_range'] !== 'all', function ($query) use ($filters) {
                $dateFilter = match ($filters['date_range']) {
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                $query->where('tour_date', '>=', $dateFilter);
            })
            ->with(['passengers'])
            ->orderBy('tour_date', 'desc')
            ->get();

        $dateRangeLabels = [
            'all' => 'All Time',
            'month' => 'Last 30 Days',
            'quarter' => 'Last Quarter',
            'year' => 'Last Year',
        ];

        $pdf = Pdf::loadView('pdf.tour-report', [
            'tours' => $tours,
            'metrics' => $metrics,
            'filters' => array_merge($filters, ['date_range_label' => $dateRangeLabels[$filters['date_range']] ?? 'All Time']),
        ])->setPaper('a4', 'landscape');

        $filename = 'tour-report-'.now()->format('Y-m-d-His').'.pdf';

        return $pdf->download($filename);
    }
}
