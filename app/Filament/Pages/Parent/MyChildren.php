<?php

namespace App\Filament\Pages\Parent;

use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Services\Academics\RankingService;
use App\Services\Learning\LearningAccess;
use App\Support\RoleGate;
use Filament\Pages\Page;

class MyChildren extends Page
{
    protected static ?string $title = 'My Children';

    protected static ?string $slug = 'my-children';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.parent.my-children';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'My Children';
    }

    public static function getNavigationLabel(): string
    {
        return 'My Children';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::is('parent') && RoleGate::can('children.view');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function childrenCards(): array
    {
        $children = app(LearningAccess::class)->childrenForParent(RoleGate::user());
        $ranking = app(RankingService::class);

        return $children->map(function ($member) use ($ranking) {
            $enrollment = StudentEnrollment::query()
                ->active()
                ->with('class')
                ->where('member_id', $member->id)
                ->latest()
                ->first();

            $results = $ranking->studentResults((int) $member->id);

            $weekBase = StudentAttendance::query()
                ->where('student_id', $member->id)
                ->whereHas('session', fn ($q) => $q->whereBetween('session_date', [now()->startOfWeek(), now()->endOfWeek()]));
            $total = (clone $weekBase)->count();
            $present = (clone $weekBase)->where('status', 'Present')->count();
            $rate = $total > 0 ? round(($present / $total) * 100, 1) : null;

            return [
                'id' => $member->id,
                'name' => $member->full_name,
                'class' => $enrollment?->class?->name ?? __('Not enrolled'),
                'average' => $results['semester_average'] ?? null,
                'rank' => $results['overall_rank'] ?? null,
                'cohort' => $results['cohort_size'] ?? 0,
                'attendance_rate' => $rate,
            ];
        })->all();
    }
}
