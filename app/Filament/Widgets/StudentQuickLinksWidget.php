<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Student\ClassAnnouncements;
use App\Filament\Pages\Student\ClassMaterials;
use App\Filament\Pages\Student\MyAttendance;
use App\Filament\Pages\Student\MyHomework;
use App\Filament\Pages\Student\MyResults;
use App\Filament\Pages\Student\RequestWithdrawal;
use App\Support\RoleGate;
use Filament\Widgets\Widget;

class StudentQuickLinksWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.student-quick-links';

    public static function canView(): bool
    {
        return RoleGate::is('student');
    }

    /**
     * @return list<array{label: string, description: string, url: string, tour: string, icon: string}>
     */
    protected function getViewData(): array
    {
        return [
            'links' => [
                [
                    'label' => __('Class Announcements'),
                    'description' => __('Notices for your class (exams, schedule, reminders).'),
                    'url' => ClassAnnouncements::getUrl(),
                    'tour' => 'tile-class-announcements',
                    'icon' => 'heroicon-o-megaphone',
                ],
                [
                    'label' => __('Homework'),
                    'description' => __('Assignments from your teachers.'),
                    'url' => MyHomework::getUrl(),
                    'tour' => 'tile-homework',
                    'icon' => 'heroicon-o-clipboard-document-list',
                ],
                [
                    'label' => __('Class Materials'),
                    'description' => __('Files shared with your class.'),
                    'url' => ClassMaterials::getUrl(),
                    'tour' => 'tile-class-materials',
                    'icon' => 'heroicon-o-folder-open',
                ],
                [
                    'label' => __('My Results'),
                    'description' => __('Scores and ranks for your class.'),
                    'url' => MyResults::getUrl(),
                    'tour' => 'tile-results',
                    'icon' => 'heroicon-o-academic-cap',
                ],
                [
                    'label' => __('My Attendance'),
                    'description' => __('Your recent attendance record.'),
                    'url' => MyAttendance::getUrl(),
                    'tour' => 'tile-attendance',
                    'icon' => 'heroicon-o-clipboard-document-check',
                ],
                [
                    'label' => __('Request withdrawal'),
                    'description' => __('Apply to leave your current class.'),
                    'url' => RequestWithdrawal::getUrl(),
                    'tour' => 'tile-withdrawal',
                    'icon' => 'heroicon-o-arrow-right-start-on-rectangle',
                ],
            ],
        ];
    }
}
