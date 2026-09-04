<?php

namespace App\Filament\Widgets\Stats;

use App\Filament\Pages\Student\ClassAnnouncements;
use App\Filament\Pages\Student\MyAttendance;
use App\Filament\Pages\Student\MyHomework;
use App\Models\ClassAnnouncement;
use App\Models\HomeworkAssignment;
use App\Models\InAppNotification;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Services\Learning\LearningAccess;
use App\Support\RoleGate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudentOverviewWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return RoleGate::is('student');
    }

    protected function getStats(): array
    {
        $user = RoleGate::user();
        $memberId = $user?->member_id;
        $classIds = app(LearningAccess::class)->classIdsForUser($user);

        $enrollment = $memberId
            ? StudentEnrollment::query()->active()->where('member_id', $memberId)->with('class')->latest()->first()
            : null;

        $attendanceBase = $memberId
            ? StudentAttendance::query()
                ->where('student_id', $memberId)
                ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [now()->startOfWeek(), now()->endOfWeek()]))
            : null;

        $total = $attendanceBase?->count() ?? 0;
        $present = $memberId
            ? StudentAttendance::query()
                ->where('student_id', $memberId)
                ->where('status', 'Present')
                ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [now()->startOfWeek(), now()->endOfWeek()]))
                ->count()
            : 0;
        $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;

        $unreadAnnouncements = 0;
        $upcomingHomework = 0;
        if ($classIds !== []) {
            $unreadAnnouncements = InAppNotification::query()
                ->where('user_id', $user->id)
                ->where('event', 'class.announcement')
                ->whereNull('read_at')
                ->count();

            $upcomingHomework = HomeworkAssignment::query()
                ->published()
                ->whereIn('class_id', $classIds)
                ->where(function ($q) {
                    $q->whereNull('due_at')->orWhere('due_at', '>=', now()->startOfWeek());
                })
                ->where(function ($q) {
                    $q->whereNull('due_at')->orWhere('due_at', '<=', now()->endOfWeek()->addDays(7));
                })
                ->count();
        }

        $noticeCount = $unreadAnnouncements ?: ClassAnnouncement::query()
            ->published()
            ->whereIn('class_id', $classIds ?: [0])
            ->count();

        return [
            Stat::make(__('Class'), $enrollment?->class?->name ?? __('Not enrolled'))
                ->icon('heroicon-o-building-library')
                ->color('info')
                ->url(ClassAnnouncements::getUrl()),
            Stat::make(__('Class notices'), (string) $noticeCount)
                ->description($unreadAnnouncements ? __('Unread') : __('For your class'))
                ->icon('heroicon-o-megaphone')
                ->color('warning')
                ->url(ClassAnnouncements::getUrl()),
            Stat::make(__('Homework due soon'), (string) $upcomingHomework)
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->url(MyHomework::getUrl()),
            Stat::make(__("This week's attendance"), "{$rate}%")
                ->icon('heroicon-o-check-circle')
                ->color($rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger'))
                ->url(MyAttendance::getUrl()),
        ];
    }
}
