<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Parent\MyChildren;
use App\Filament\Pages\Student\ClassAnnouncements;
use App\Filament\Pages\Student\ClassMaterials;
use App\Filament\Pages\Student\MyHomework;
use App\Support\RoleGate;
use Filament\Widgets\Widget;

class ParentChildrenWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.parent-children-links';

    public static function canView(): bool
    {
        return RoleGate::is('parent');
    }

    protected function getViewData(): array
    {
        return [
            'links' => [
                [
                    'label' => __('My Children'),
                    'description' => __('Results and attendance for linked kids.'),
                    'url' => MyChildren::getUrl(),
                ],
                [
                    'label' => __('Class Announcements'),
                    'description' => __('Notices for your children’s classes.'),
                    'url' => ClassAnnouncements::getUrl(),
                ],
                [
                    'label' => __('Homework'),
                    'description' => __('Assignments shared with their class.'),
                    'url' => MyHomework::getUrl(),
                ],
                [
                    'label' => __('Class Materials'),
                    'description' => __('Files shared with their class.'),
                    'url' => ClassMaterials::getUrl(),
                ],
            ],
        ];
    }
}
