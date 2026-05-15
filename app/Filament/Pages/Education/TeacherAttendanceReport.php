<?php

namespace App\Filament\Pages\Education;

use App\Models\AcademicYear;
use Filament\Schemas\Schema;
use App\Models\Teacher;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TeacherAttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    protected string $view = 'filament.pages.education.teacher-attendance-report';

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'academic_year_id' => AcademicYear::where('status', 'Active')->first()?->id,
            'teacher_id' => null,
            'date_range' => 'month',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                \Filament\Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(AcademicYear::pluck('name', 'id'))
                    ->required()
                    ->reactive(),

                \Filament\Forms\Components\Select::make('teacher_id')
                    ->label('Teacher')
                    ->options(function (callable $get) {
                        $yearId = $get('academic_year_id');
                        if (!$yearId) {
                            return [];
                        }

                        return Teacher::where('status', 'Active')
                            ->whereHas('assignments', function ($query) use ($yearId) {
                                $query->where('academic_year_id', $yearId);
                            })
                            ->with('assignments')
                            ->get()
                            ->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->full_name]);
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive(),

                \Filament\Forms\Components\Select::make('date_range')
                    ->label('Date Range')
                    ->options([
                        'week' => 'Last Week',
                        'month' => 'Last Month',
                        'quarter' => 'Last Quarter',
                        'year' => 'Last Year',
                    ])
                    ->default('month')
                    ->required(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('teacher.full_name')
                    ->label('Teacher')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_sessions')
                    ->label('Total Sessions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('present_sessions')
                    ->label('Present')
                    ->sortable(),

                Tables\Columns\TextColumn::make('attendance_rate')
                    ->label('Attendance Rate')
                    ->sortable()
                    ->getStateUsing(fn ($record) => number_format($record->attendance_rate, 2) . '%'),
            ])
            ->filters([
                SelectFilter::make('teacher_id')
                    ->label('Teacher')
                    ->options(function () {
                        return Teacher::where('status', 'Active')
                            ->whereHas('assignments', function ($query) {
                                $query->where('academic_year_id', AcademicYear::where('status', 'Active')->first()?->id);
                            })
                            ->with('assignments')
                            ->get()
                            ->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->full_name]);
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('date_range')
                    ->label('Date Range')
                    ->options([
                        'week' => 'Last Week',
                        'month' => 'Last Month',
                        'quarter' => 'Last Quarter',
                        'year' => 'Last Year',
                    ]),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        try {
            $filters = $this->form->getState();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return Teacher::query()->whereRaw('1 = 0');
        }

        $activeYear = AcademicYear::where('status', 'Active')->first();

        $query = Teacher::query()
            ->where('status', 'Active')
            ->whereHas('assignments', function ($query) use ($activeYear) {
                $query->where('academic_year_id', $activeYear?->id);
            })
            ->with([
                'assignments' => function ($query) use ($filters) {
                    $query->where('academic_year_id', $filters['academic_year_id'] ?? $activeYear?->id);
                },
            ]);

        if (!empty($filters['teacher_id'])) {
            $query->where('teachers.id', $filters['teacher_id']);
        }

        $dateThreshold = match ($filters['date_range'] ?? 'month') {
            'week' => now()->subWeek()->toDateString(),
            'month' => now()->subMonth()->toDateString(),
            'quarter' => now()->subQuarter()->toDateString(),
            'year' => now()->subYear()->toDateString(),
            default => now()->subMonth()->toDateString(),
        };

        return $query->selectRaw("
            teachers.*,
            (
                SELECT COUNT(DISTINCT s.id)
                FROM teacher_attendance ta
                JOIN attendance_sessions s ON s.id = ta.session_id
                WHERE ta.teacher_id = teachers.id
                AND s.academic_year_id = ?
                AND s.session_date >= ?
            ) as total_sessions,
            (
                SELECT COUNT(DISTINCT s.id)
                FROM teacher_attendance ta
                JOIN attendance_sessions s ON s.id = ta.session_id
                WHERE ta.teacher_id = teachers.id
                AND s.academic_year_id = ?
                AND s.session_date >= ?
                AND ta.attendance_status = 'Present'
            ) as present_sessions
        ", [
            $activeYear?->id ?? 1,
            $dateThreshold,
            $activeYear?->id ?? 1,
            $dateThreshold,
        ])
            ->selectRaw("
                CASE 
                    WHEN total_sessions = 0 THEN 0
                    ELSE ROUND((present_sessions / total_sessions) * 100, 2)
                END as attendance_rate
            ");
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->can('page.report.teacher-attendance');
    }
}
