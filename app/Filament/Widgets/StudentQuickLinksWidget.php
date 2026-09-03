<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Student\MyAttendance;
use App\Filament\Pages\Student\MyResults;
use App\Filament\Pages\Student\RequestWithdrawal;
use App\Filament\Support\NavHubRegistry;
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
     * @return list<array{label: string, description: string, url: string, tour: string}>
     */
    protected function getViewData(): array
    {
        $libraryUrl = NavHubRegistry::accessibleTabsForHub('library') !== []
            ? NavHubRegistry::hubUrl('library')
            : url('/library');

        $announcementsUrl = NavHubRegistry::accessibleTabsForHub('site-notices') !== []
            ? NavHubRegistry::hubUrl('site-notices')
            : null;

        $links = [
            [
                'label' => __('My Results'),
                'description' => __('Approved marklists for your class.'),
                'url' => MyResults::getUrl(),
                'tour' => 'tile-results',
            ],
            [
                'label' => __('My Attendance'),
                'description' => __('Your recent attendance record.'),
                'url' => MyAttendance::getUrl(),
                'tour' => 'tile-attendance',
            ],
            [
                'label' => __('Library'),
                'description' => __('Books, worksheets, and documents.'),
                'url' => $libraryUrl,
                'tour' => 'tile-library',
            ],
            [
                'label' => __('Request withdrawal'),
                'description' => __('Apply to leave your current class.'),
                'url' => RequestWithdrawal::getUrl(),
                'tour' => 'tile-withdrawal',
            ],
        ];

        if ($announcementsUrl) {
            $links[] = [
                'label' => __('Announcements'),
                'description' => __('Church notices for students.'),
                'url' => $announcementsUrl,
                'tour' => 'tile-announcements',
            ];
        }

        return ['links' => $links];
    }
}
