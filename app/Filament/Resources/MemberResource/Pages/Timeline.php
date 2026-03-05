<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\AcademicYear;
use App\Models\Member;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\Url;

class Timeline extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.member.pages.timeline';

    protected static ?string $title = 'Member Timeline';

    // ── Filter Properties ──────────────────────────────────────
    public ?string $eventTypeFilter = 'all';
    public ?string $dateRangeFilter = 'all';
    public ?string $academicYearFilter = 'all';

    // ── Filter Options ─────────────────────────────────────────

    public function getAcademicYears(): array
    {
        return AcademicYear::orderByDesc('start_date')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getEventTypeOptions(): array
    {
        return [
            'all'          => 'All Events',
            'attendance'   => 'Attendance',
            'contribution' => 'Contributions',
            'enrollment'   => 'Enrollments',
            'education'    => 'Education',
            'group'        => 'Group Assignments',
            'guardian'      => 'Parent / Guardian',
            'tour'         => 'Tours',
        ];
    }

    public function getDateRangeOptions(): array
    {
        return [
            'all'     => 'All Time',
            'week'    => 'Past Week',
            'month'   => 'Past Month',
            'quarter' => 'Past 3 Months',
            'year'    => 'Past Year',
        ];
    }

    // ── Member Profile Summary ─────────────────────────────────

    public function getMemberProfile(): array
    {
        $member = $this->record;

        $currentGroup = $member->groupAssignments()
            ->whereNull('effective_to')
            ->with('group')
            ->latest('effective_from')
            ->first();

        $currentEnrollment = $member->studentEnrollments()
            ->where('status', 'Enrolled')
            ->with(['class', 'academicYear'])
            ->latest('enrolled_date')
            ->first();

        return [
            'member_type'  => $member->member_type ?? 'N/A',
            'department'   => $member->department()->withoutGlobalScopes()->first()?->name_en ?? 'N/A',
            'current_group' => $currentGroup?->group?->name ?? 'N/A',
            'current_class' => $currentEnrollment ? ($currentEnrollment->class?->name ?? 'N/A') : 'N/A',
            'current_academic_year' => $currentEnrollment?->academicYear?->name ?? null,
        ];
    }

    // ── Statistics ──────────────────────────────────────────────

    public function getStatistics(): array
    {
        $member = $this->record;

        return [
            'attendance_present' => $member->attendanceRecords()->where('status', 'Present')->count(),
            'attendance_total'   => $member->attendanceRecords()->count(),
            'contributions_sum'  => $member->contributions()->sum('amount'),
            'contributions_count' => $member->contributions()->count(),
            'enrollments_count'  => $member->studentEnrollments()->count(),
            'groups_count'       => $member->groupAssignments()->count(),
            'tours_count'        => $member->tourPassengers()->count(),
            'education_level'    => $member->educationHistory()->latest()->first()?->education_level ?? 'N/A',
        ];
    }

    // ── Timeline Events ────────────────────────────────────────

    public function getTimelineEvents(): array
    {
        $collections = [];

        // Helper: date cutoff for range filter
        $dateCutoff = $this->getDateCutoff();
        $isFilteringType = $this->eventTypeFilter !== 'all';

        // ──── Attendance ────
        if (!$isFilteringType || $this->eventTypeFilter === 'attendance') {
            $collections[] = $this->record->attendanceRecords()
                ->when($dateCutoff, fn ($q) => $q->where('event_date', '>=', $dateCutoff))
                ->orderByDesc('event_date')
                ->get()
                ->map(fn ($r) => [
                    'id'          => $r->id,
                    'type'        => 'attendance',
                    'title'       => 'Attendance Recorded',
                    'description' => "{$r->status}" . ($r->event_type ? " — {$r->event_type}" : '') . ($r->notes ? ": {$r->notes}" : ''),
                    'date'        => $r->event_date?->toDateString() ?? $r->created_at->toDateString(),
                    'time'        => $r->created_at?->toTimeString(),
                    'status'      => $r->status ?? 'Recorded',
                    'color'       => match ($r->status) {
                        'Present' => 'success',
                        'Absent'  => 'danger',
                        'Late'    => 'warning',
                        'Excused', 'Permission' => 'info',
                        default   => 'gray',
                    },
                ]);
        }

        // ──── Contributions ────
        if (!$isFilteringType || $this->eventTypeFilter === 'contribution') {
            $collections[] = $this->record->contributions()
                ->when($dateCutoff, fn ($q) => $q->where('payment_date', '>=', $dateCutoff))
                ->when($this->academicYearFilter !== 'all', fn ($q) => $q->where('academic_year_id', $this->academicYearFilter))
                ->with('academicYear')
                ->orderByDesc('payment_date')
                ->get()
                ->map(fn ($r) => [
                    'id'          => $r->id,
                    'type'        => 'contribution',
                    'title'       => 'Contribution — ' . ($r->month_name ?? 'Payment'),
                    'description' => number_format($r->amount, 2) . ' ETB' .
                                     ($r->payment_method ? " via {$r->payment_method}" : '') .
                                     ($r->academicYear ? " ({$r->academicYear->name})" : ''),
                    'date'        => $r->payment_date?->toDateString() ?? $r->created_at->toDateString(),
                    'time'        => null,
                    'status'      => $r->is_archived ? 'Archived' : 'Paid',
                    'color'       => $r->is_archived ? 'gray' : 'primary',
                ]);
        }

        // ──── Student Enrollments (classes learned) ────
        if (!$isFilteringType || $this->eventTypeFilter === 'enrollment') {
            $collections[] = $this->record->studentEnrollments()
                ->when($dateCutoff, fn ($q) => $q->where('enrolled_date', '>=', $dateCutoff))
                ->when($this->academicYearFilter !== 'all', fn ($q) => $q->where('academic_year_id', $this->academicYearFilter))
                ->with(['class', 'academicYear'])
                ->orderByDesc('enrolled_date')
                ->get()
                ->map(fn ($r) => [
                    'id'          => $r->id,
                    'type'        => 'enrollment',
                    'title'       => 'Class Enrollment',
                    'description' => ($r->class?->name ?? 'Unknown Class') .
                                     ($r->academicYear ? " — {$r->academicYear->name}" : '') .
                                     ($r->completion_date ? " (Completed {$r->completion_date->toFormattedDateString()})" : ''),
                    'date'        => $r->enrolled_date?->toDateString() ?? $r->created_at->toDateString(),
                    'time'        => null,
                    'status'      => $r->status ?? 'Enrolled',
                    'color'       => match ($r->status) {
                        'Completed'  => 'success',
                        'Enrolled'   => 'info',
                        'Withdrawn'  => 'danger',
                        default      => 'gray',
                    },
                ]);
        }

        // ──── Education History ────
        if (!$isFilteringType || $this->eventTypeFilter === 'education') {
            $collections[] = $this->record->educationHistory()
                ->when($dateCutoff, fn ($q) => $q->where('created_at', '>=', $dateCutoff))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($r) => [
                    'id'          => $r->id,
                    'type'        => 'education',
                    'title'       => 'Education Milestone',
                    'description' => ($r->education_level ?? 'N/A') .
                                     ($r->school_name ? " at {$r->school_name}" : '') .
                                     ($r->education_department ? " — {$r->education_department}" : '') .
                                     ($r->is_current ? ' (Current)' : ''),
                    'date'        => $r->created_at->toDateString(),
                    'time'        => null,
                    'status'      => $r->is_current ? 'Current' : 'Completed',
                    'color'       => $r->is_current ? 'info' : 'success',
                ]);
        }

        // ──── Group Assignment History ────
        if (!$isFilteringType || $this->eventTypeFilter === 'group') {
            $collections[] = $this->record->groupAssignments()
                ->when($dateCutoff, fn ($q) => $q->where('effective_from', '>=', $dateCutoff))
                ->with('group')
                ->orderByDesc('effective_from')
                ->get()
                ->map(fn ($r) => [
                    'id'          => $r->id,
                    'type'        => 'group',
                    'title'       => is_null($r->effective_to) ? 'Group Assignment (Active)' : 'Group Assignment (Ended)',
                    'description' => ($r->group?->name ?? 'Unknown Group') .
                                     ' — From ' . ($r->effective_from?->toFormattedDateString() ?? 'N/A') .
                                     ($r->effective_to ? ' to ' . $r->effective_to->toFormattedDateString() : ' (ongoing)'),
                    'date'        => $r->effective_from?->toDateString() ?? $r->created_at->toDateString(),
                    'time'        => null,
                    'status'      => is_null($r->effective_to) ? 'Active' : 'Ended',
                    'color'       => is_null($r->effective_to) ? 'success' : 'secondary',
                ]);
        }

        // ──── Parent/Guardian Assignments ────
        if (!$isFilteringType || $this->eventTypeFilter === 'guardian') {
            $collections[] = $this->record->parentGuardians()
                ->when($dateCutoff, fn ($q) => $q->where('created_at', '>=', $dateCutoff))
                ->with('parent')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($r) {
                    $parentName = $r->parent?->full_name ?? ($r->parent?->first_name ?? 'Unknown');
                    return [
                        'id'          => $r->id,
                        'type'        => 'guardian',
                        'title'       => 'Parent/Guardian Assigned',
                        'description' => $parentName . ($r->relationship ? " ({$r->relationship})" : ''),
                        'date'        => $r->created_at->toDateString(),
                        'time'        => $r->created_at->toTimeString(),
                        'status'      => 'Active',
                        'color'       => 'warning',
                    ];
                });
        }

        // ──── Tour Participation ────
        if (!$isFilteringType || $this->eventTypeFilter === 'tour') {
            $collections[] = $this->record->tourPassengers()
                ->when($dateCutoff, fn ($q) => $q->where('created_at', '>=', $dateCutoff))
                ->with('tour')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($r) => [
                    'id'          => $r->id,
                    'type'        => 'tour',
                    'title'       => 'Tour Participation',
                    'description' => 'Registered for ' . ($r->tour?->place ?? 'a tour'),
                    'date'        => $r->created_at->toDateString(),
                    'time'        => $r->created_at->toTimeString(),
                    'status'      => $r->status ?? 'Registered',
                    'color'       => match ($r->status ?? '') {
                        'Attended'  => 'success',
                        'Confirmed' => 'primary',
                        'Cancelled' => 'danger',
                        default     => 'warning',
                    },
                ]);
        }

        // Merge, sort, return
        return collect($collections)
            ->flatten(1)
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function getDateCutoff(): ?\Carbon\Carbon
    {
        return match ($this->dateRangeFilter) {
            'week'    => now()->subWeek(),
            'month'   => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year'    => now()->subYear(),
            default   => null,
        };
    }

    public function getEventIcon(string $type, string $status): string
    {
        $icons = [
            'attendance' => [
                'Present'    => 'heroicon-o-check-circle',
                'Absent'     => 'heroicon-o-x-circle',
                'Late'       => 'heroicon-o-clock',
                'Excused'    => 'heroicon-o-shield-check',
                'Permission' => 'heroicon-o-document-text',
            ],
            'contribution'  => 'heroicon-o-banknotes',
            'enrollment'    => 'heroicon-o-academic-cap',
            'education'     => 'heroicon-o-book-open',
            'guardian'      => 'heroicon-o-users',
            'group'         => 'heroicon-o-user-group',
            'tour'          => 'heroicon-o-map',
            'status_change' => 'heroicon-o-cog-6-tooth',
        ];

        return $icons[$type][$status] ?? $icons[$type] ?? 'heroicon-o-information-circle';
    }

    public function getEventColor(string $type, string $status): string
    {
        $colors = [
            'attendance' => [
                'Present'    => 'success',
                'Absent'     => 'danger',
                'Late'       => 'warning',
                'Excused'    => 'info',
                'Permission' => 'gray',
            ],
            'contribution'  => 'primary',
            'enrollment'    => 'info',
            'education'     => 'info',
            'guardian'      => 'warning',
            'group'         => 'secondary',
            'tour'          => 'primary',
            'status_change' => 'gray',
        ];

        return $colors[$type][$status] ?? $colors[$type] ?? 'gray';
    }
}
