<?php

namespace App\Filament\Pages\Student;

use App\Models\HomeworkAssignment;
use App\Services\Learning\LearningAccess;
use App\Support\RoleGate;
use Filament\Pages\Page;

class MyHomework extends Page
{
    protected static ?string $title = 'Homework';

    protected static ?string $slug = 'my-homework';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.student.my-homework';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'My Learning';
    }

    public static function getNavigationLabel(): string
    {
        return 'Homework';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (RoleGate::is('student') && RoleGate::can('homework.view_own'))
            || (RoleGate::is('parent') && RoleGate::can('homework.view_linked'));
    }

    /**
     * @return list<\App\Models\HomeworkAssignment>
     */
    public function items(): array
    {
        $classIds = app(LearningAccess::class)->classIdsForUser(RoleGate::user());

        if ($classIds === []) {
            return [];
        }

        return HomeworkAssignment::query()
            ->published()
            ->with(['class', 'subject'])
            ->whereIn('class_id', $classIds)
            ->orderByRaw('due_at is null, due_at asc')
            ->limit(50)
            ->get()
            ->all();
    }
}
