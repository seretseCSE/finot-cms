<?php

namespace App\Filament\Pages\Student;

use App\Models\ClassMaterial;
use App\Services\Learning\LearningAccess;
use App\Support\RoleGate;
use Filament\Pages\Page;

class ClassMaterials extends Page
{
    protected static ?string $title = 'Class Materials';

    protected static ?string $slug = 'class-materials-student';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.student.class-materials';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder-open';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'My Learning';
    }

    public static function getNavigationLabel(): string
    {
        return 'Class Materials';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (RoleGate::is('student') && RoleGate::can('class_materials.view_own'))
            || (RoleGate::is('parent') && RoleGate::can('class_materials.view_linked'));
    }

    /**
     * @return list<\App\Models\ClassMaterial>
     */
    public function items(): array
    {
        $classIds = app(LearningAccess::class)->classIdsForUser(RoleGate::user());

        if ($classIds === []) {
            return [];
        }

        return ClassMaterial::query()
            ->published()
            ->with(['class', 'subject'])
            ->whereIn('class_id', $classIds)
            ->latest('published_at')
            ->limit(50)
            ->get()
            ->all();
    }
}
