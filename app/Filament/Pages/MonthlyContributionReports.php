<?php

namespace App\Filament\Pages;

use App\Models\AcademicYear;
use Filament\Schemas\Schema;
use App\Models\Contribution;
use App\Models\Department;
use App\Models\MemberGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use BackedEnum;

class MonthlyContributionReports extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.monthly-contribution-reports';

    protected static ?string $navigationLabel = 'Monthly Reports';

    protected static ?string $title = 'Monthly Contribution Reports';

    public static function getNavigationGroup(): ?string
    {
        return 'Contributions';
    }

    protected static ?int $navigationSort = 3;

    public ?int $academicYear = null;

    public ?int $department = null;

    public ?int $group = null;

    public ?string $type = null;

    public ?string $status = null;

    public ?int $month = null;

    public Collection $monthlyReports;

    public array $months = [
        1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
        5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
        9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehasse',
    ];

    public function mount(): void
    {
        // Set default academic year to current year
        $currentYear = AcademicYear::where('is_current', true)->first();
        if ($currentYear) {
            $this->academicYear = $currentYear->id;
        }

        $this->loadReports();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                Select::make('academicYear')
                    ->label('Academic Year')
                    ->options(AcademicYear::pluck('name', 'id'))
                    ->default(fn () => AcademicYear::where('is_current', true)->first()?->id)
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadReports()),

                Select::make('department')
                    ->label('Department')
                    ->options(Department::pluck('name_en', 'id'))
                    ->placeholder('All Departments')
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadReports()),

                Select::make('group')
                    ->label('Group')
                    ->options(MemberGroup::pluck('name', 'id'))
                    ->placeholder('All Groups')
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadReports()),

                Select::make('type')
                    ->label('Member Type')
                    ->options([
                        'student' => 'Student',
                        'teacher' => 'Teacher',
                        'staff' => 'Staff',
                        'volunteer' => 'Volunteer',
                    ])
                    ->placeholder('All Types')
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadReports()),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'graduated' => 'Graduated',
                        'suspended' => 'Suspended',
                    ])
                    ->placeholder('All Statuses')
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadReports()),

                Select::make('month')
                    ->label('Month')
                    ->options($this->months)
                    ->placeholder('All Months')
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadReports()),
            ])
            ->columns(3);
    }

    public function loadReports(): void
    {
        if (! $this->academicYear) {
            $this->monthlyReports = collect();
            return;
        }

        $query = Contribution::where('academic_year_id', $this->academicYear)
            ->where('is_archived', false)
            ->with(['member' => function ($query) {
                $query->with(['currentGroupAssignment.group', 'department']);
            }]);

        // Apply filters
        if ($this->department) {
            $query->whereHas('member', function (Builder $q) {
                $q->where('department_id', $this->department);
            });
        }

        if ($this->group) {
            $query->whereHas('member.currentGroupAssignment', function (Builder $q) {
                $q->where('group_id', $this->group);
            });
        }

        if ($this->type) {
            $query->whereHas('member', function (Builder $q) {
                $q->where('member_type', $this->type);
            });
        }

        if ($this->status) {
            $query->whereHas('member', function (Builder $q) {
                $q->where('status', $this->status);
            });
        }

        if ($this->month) {
            $monthName = $this->months[$this->month];
            $query->where('month_name', $monthName);
        }

        $contributions = $query->get();

        // Group contributions by month
        $monthlyData = [];
        foreach ($contributions as $contribution) {
            $monthNum = array_search($contribution->month_name, $this->months);
            if ($monthNum === false) {
                continue;
            }

            if (! isset($monthlyData[$monthNum])) {
                $monthlyData[$monthNum] = [
                    'month_name' => $contribution->month_name,
                    'month_num' => $monthNum,
                    'contributions' => collect(),
                    'total_amount' => 0,
                    'member_count' => 0,
                ];
            }

            $monthlyData[$monthNum]['contributions']->push($contribution);
            $monthlyData[$monthNum]['total_amount'] += $contribution->amount;
            $monthlyData[$monthNum]['member_count'] = $monthlyData[$monthNum]['contributions']
                ->pluck('member_id')
                ->unique()
                ->count();
        }

        // Sort by month number
        ksort($monthlyData);
        $this->monthlyReports = collect($monthlyData);
    }

    public function getFilterOptions(): array
    {
        return [
            'academicYears' => AcademicYear::pluck('name', 'id'),
            'departments' => Department::pluck('name_en', 'id'),
            'groups' => MemberGroup::pluck('name', 'id'),
            'types' => [
                'student' => 'Student',
                'teacher' => 'Teacher',
                'staff' => 'Staff',
                'volunteer' => 'Volunteer',
            ],
            'statuses' => [
                'active' => 'Active',
                'inactive' => 'Inactive',
                'graduated' => 'Graduated',
                'suspended' => 'Suspended',
            ],
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'admin', 'finance_admin', 'treasurer']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->iconSize('xs')
                ->size('xs')
                ->color('success')
                ->visible(fn () => ! auth()->user()?->hasRole('nibret_hisab_head'))
                ->action(function () {
                    // Export logic here
                    Notification::make()
                        ->title('Export Started')
                        ->body('The monthly reports are being exported to Excel.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
