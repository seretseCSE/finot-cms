<?php

namespace App\Filament\Pages\Student;

use App\Models\AttendanceRecord;
use App\Models\StudentAttendance;
use App\Support\RoleGate;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MyAttendance extends Page
{
    protected static ?string $title = 'My Attendance';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.student.my-attendance';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'My Learning';
    }

    public static function getNavigationLabel(): string
    {
        return 'My Attendance';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::is('student') && RoleGate::can('attendance.view_own');
    }

    /**
     * @return Collection<int, StudentAttendance>
     */
    public function classRecords(): Collection
    {
        $memberId = RoleGate::user()?->member_id;

        if (! $memberId) {
            return collect();
        }

        return StudentAttendance::query()
            ->with(['session.class'])
            ->where('student_id', $memberId)
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    public function eventRecords(): Collection
    {
        $memberId = RoleGate::user()?->member_id;

        if (! $memberId) {
            return collect();
        }

        return AttendanceRecord::query()
            ->where('member_id', $memberId)
            ->orderByDesc('event_date')
            ->limit(100)
            ->get();
    }
}
