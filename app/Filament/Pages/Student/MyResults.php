<?php

namespace App\Filament\Pages\Student;

use App\Services\Academics\RankingService;
use App\Support\RoleGate;
use Filament\Pages\Page;

class MyResults extends Page
{
    protected static ?string $title = 'My Results';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.student.my-results';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'My Learning';
    }

    public static function getNavigationLabel(): string
    {
        return 'My Results';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::is('student') && RoleGate::can('results.view_own');
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     semester_average: float|null,
     *     year_average: float|null,
     *     overall_rank: int|null,
     *     cohort_size: int
     * }
     */
    public function summary(): array
    {
        $memberId = RoleGate::user()?->member_id;

        if (! $memberId) {
            return [
                'items' => [],
                'semester_average' => null,
                'year_average' => null,
                'overall_rank' => null,
                'cohort_size' => 0,
            ];
        }

        return app(RankingService::class)->studentResults((int) $memberId);
    }
}
