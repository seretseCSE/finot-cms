<?php

namespace App\Filament\Pages\Student;

use App\Models\ClassAnnouncement;
use App\Services\Learning\LearningAccess;
use App\Support\RoleGate;
use Filament\Pages\Page;

class ClassAnnouncements extends Page
{
    protected static ?string $title = 'Class Announcements';

    protected static ?string $slug = 'class-announcements-student';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.student.class-announcements';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'My Learning';
    }

    public static function getNavigationLabel(): string
    {
        return 'Class Announcements';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (RoleGate::is('student') && RoleGate::can('class_announcements.view_own'))
            || (RoleGate::is('parent') && RoleGate::can('class_announcements.view_linked'));
    }

    /**
     * @return list<\App\Models\ClassAnnouncement>
     */
    public function items(): array
    {
        $classIds = app(LearningAccess::class)->classIdsForUser(RoleGate::user());

        if ($classIds === []) {
            return [];
        }

        return ClassAnnouncement::query()
            ->published()
            ->with('class')
            ->whereIn('class_id', $classIds)
            ->latest('published_at')
            ->limit(50)
            ->get()
            ->all();
    }
}
