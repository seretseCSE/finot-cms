<?php

namespace App\Filament\Pages;

use App\Exports\ContributionExport;
use App\Jobs\ProcessExportJob;
use Filament\Schemas\Schema;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\MemberGroup;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class ContributionReport extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    protected static ?string $title = 'Contribution Report';

    protected string $view = 'filament.pages.contribution-report';

    public $reportData = [
        'contributions' => [],
        'topContributors' => [],
    ];

    public ?string $selectedAcademicYear = null;

    public array $academicYears = [];

    public ?int $group_id = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?int $academic_year_id = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('superadmin') || $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
    }

    public function mount(): void
    {
        $this->academicYears = AcademicYear::pluck('name', 'id')->toArray();
        $activeYear = AcademicYear::where('status', 'Active')->first();
        if ($activeYear) {
            $this->selectedAcademicYear = (string) $activeYear->id;
            $this->form->fill([
                'academic_year_id' => $activeYear->id,
            ]);
            $this->academic_year_id = $activeYear->id;
        } else {
            $this->form->fill();
        }

        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options($this->academicYears)
                    ->placeholder('All Years'),

                Select::make('group_id')
                    ->label('Member Group')
                    ->options(MemberGroup::pluck('name', 'id'))
                    ->placeholder('All Groups'),

                DatePicker::make('start_date')
                    ->label('Start Date'),

                DatePicker::make('end_date')
                    ->label('End Date'),
            ])
            ->columns(4);
    }

    public function applyFilters(): void
    {
        $data = $this->form->getState();
        $this->group_id = $data['group_id'] ?? null;
        $this->academic_year_id = $data['academic_year_id'] ?? null;
        $this->start_date = $data['start_date'] ?? null;
        $this->end_date = $data['end_date'] ?? null;

        $this->selectedAcademicYear = $this->academic_year_id ? strval($this->academic_year_id) : 'all';

        $this->loadData();
    }

    public function resetFilters(): void
    {
        $this->form->fill([
            'academic_year_id' => $this->selectedAcademicYear !== 'all' ? $this->selectedAcademicYear : null,
        ]);
        $this->group_id = null;
        $this->start_date = null;
        $this->end_date = null;
        $this->academic_year_id = $this->selectedAcademicYear !== 'all' ? $this->selectedAcademicYear : null;

        $this->loadData();
    }

    protected function buildQuery(Builder $query): Builder
    {
        if ($this->academic_year_id) {
            $query->where('academic_year_id', $this->academic_year_id);
        }

        if ($this->group_id) {
            $query->whereHas('member.currentGroupAssignment', function (Builder $query) {
                $query->where('group_id', $this->group_id);
            });
        }

        if ($this->start_date) {
            $query->whereDate('payment_date', '>=', $this->start_date);
        }

        if ($this->end_date) {
            $query->whereDate('payment_date', '<=', $this->end_date);
        }

        return $query;
    }

    protected function loadData(): void
    {
        $query = Contribution::with(['member.currentGroupAssignment.group', 'recordedBy'])
            ->orderBy('payment_date', 'desc');

        $contributions = $this->buildQuery($query)->get();

        // Calculate Top Contributors
        $grouped = $contributions->groupBy('member_id');
        $top = [];
        foreach ($grouped as $memberId => $memberContributions) {
            $top[] = [
                'member' => $memberContributions->first()->member,
                'total' => $memberContributions->sum('amount'),
            ];
        }

        usort($top, fn ($a, $b) => $b['total'] <=> $a['total']);
        $top = array_slice($top, 0, 5);

        $this->reportData = [
            'contributions' => $contributions,
            'topContributors' => $top,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    CheckboxList::make('columns')
                        ->label('Columns')
                        ->options(ContributionExport::availableColumns())
                        ->default(array_keys(ContributionExport::availableColumns()))
                        ->columns(2)
                        ->required(),
                    Radio::make('format')
                        ->label('Format')
                        ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (array $data) {
                    ProcessExportJob::dispatchSync(
                        exportClass: ContributionExport::class,
                        columns: $data['columns'],
                        format: $data['format'],
                        userId: auth()->id(),
                        filters: [
                            'academic_year_id' => $this->academic_year_id,
                            'group_id' => $this->group_id,
                            'start_date' => $this->start_date,
                            'end_date' => $this->end_date,
                        ],
                    );

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your export is being processed. You will be notified when it is ready.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
