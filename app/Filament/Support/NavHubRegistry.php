<?php

namespace App\Filament\Support;

use App\Filament\Pages\BeneficiaryReportPage;
use App\Filament\Pages\CharityReport;
use App\Filament\Pages\ContributionMatrix;
use App\Filament\Pages\ContributionReport;
use App\Filament\Pages\OutstandingContributions;
use App\Filament\Resources\AcademicYearResource;
use App\Filament\Resources\AidDistributionResource;
use App\Filament\Resources\AnnouncementResource;
use App\Filament\Resources\BeneficiaryResource;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\ContributionAmountResource;
use App\Filament\Resources\EventRegistrationResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\FacilityResource;
use App\Filament\Resources\FAQResource;
use App\Filament\Resources\InventoryResource;
use App\Filament\Resources\LibraryCategoryResource;
use App\Filament\Resources\LibraryResource;
use App\Filament\Resources\LossRecordResource;
use App\Filament\Resources\MediaCategoryResource;
use App\Filament\Resources\MediaResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PromotionResource;
use App\Filament\Resources\RehearsalAttendanceResource;
use App\Filament\Resources\RehearsalResource;
use App\Filament\Resources\SchoolClassResource;
use App\Filament\Resources\SongCategoryResource;
use App\Filament\Resources\SongResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\StudentEnrollmentResource;
use App\Filament\Resources\SubjectResource;
use App\Filament\Resources\TeacherAssignmentResource;
use App\Filament\Resources\TeacherResource;
use App\Filament\Resources\TermResource;
use App\Filament\Resources\TourPassengerResource;
use App\Filament\Resources\TourResource;
use App\Filament\Resources\WithdrawalRequestResource;
use App\Filament\Pages\ResourceTabHub;
use App\Support\RoleGate;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Throwable;

class NavHubRegistry
{
    /**
     * @return list<array{key: string, label: string, group: string, icon: string, sort: int, tabs: list<array{label: string, target: class-string}>}>
     */
    public static function hubs(): array
    {
        return [
            [
                'key' => 'contribution-setup',
                'label' => 'Contribution Setup',
                'group' => 'Contributions',
                'icon' => 'heroicon-o-cog-6-tooth',
                'sort' => 1,
                'tabs' => [
                    ['label' => 'Contribution Settings', 'target' => ContributionAmountResource::class],
                    ['label' => 'Contribution Form', 'target' => ContributionMatrix::class],
                ],
            ],
            [
                'key' => 'contribution-follow-up',
                'label' => 'Contribution follow-up',
                'group' => 'Contributions',
                'icon' => 'heroicon-o-exclamation-triangle',
                'sort' => 2,
                'tabs' => [
                    ['label' => 'Outstanding Contributions', 'target' => OutstandingContributions::class],
                    ['label' => 'Contribution Report', 'target' => ContributionReport::class],
                ],
            ],
            [
                'key' => 'academic-calendar',
                'label' => 'Academic calendar',
                'group' => 'Education Management',
                'icon' => 'heroicon-o-calendar-days',
                'sort' => 1,
                'tabs' => [
                    ['label' => 'Academic Years', 'target' => AcademicYearResource::class],
                    ['label' => 'Semesters', 'target' => TermResource::class],
                ],
            ],
            [
                'key' => 'classes-subjects',
                'label' => 'Classes & subjects',
                'group' => 'Education Management',
                'icon' => 'heroicon-o-building-library',
                'sort' => 2,
                'tabs' => [
                    ['label' => 'Classes', 'target' => SchoolClassResource::class],
                    ['label' => 'Subjects', 'target' => SubjectResource::class],
                ],
            ],
            [
                'key' => 'student-movement',
                'label' => 'Student movement',
                'group' => 'Education Management',
                'icon' => 'heroicon-o-arrows-right-left',
                'sort' => 3,
                'tabs' => [
                    ['label' => 'Enrollments', 'target' => StudentEnrollmentResource::class],
                    ['label' => 'Withdrawals', 'target' => WithdrawalRequestResource::class],
                    ['label' => 'Promotions', 'target' => PromotionResource::class],
                ],
            ],
            [
                'key' => 'teachers',
                'label' => 'Teachers',
                'group' => 'Education Management',
                'icon' => 'heroicon-o-user-group',
                'sort' => 4,
                'tabs' => [
                    ['label' => 'Teachers', 'target' => TeacherResource::class],
                    ['label' => 'Assignments', 'target' => TeacherAssignmentResource::class],
                ],
            ],
            [
                'key' => 'library',
                'label' => 'Library',
                'group' => 'Education Management',
                'icon' => 'heroicon-o-book-open',
                'sort' => 12,
                'tabs' => [
                    ['label' => 'Library Resources', 'target' => LibraryResource::class],
                    ['label' => 'Library Categories', 'target' => LibraryCategoryResource::class],
                ],
            ],
            [
                'key' => 'site-notices',
                'label' => 'Site notices',
                'group' => 'Content Management',
                'icon' => 'heroicon-o-megaphone',
                'sort' => 1,
                'tabs' => [
                    ['label' => 'Announcements', 'target' => AnnouncementResource::class],
                    ['label' => 'FAQs', 'target' => FAQResource::class],
                    ['label' => 'Pages', 'target' => PageResource::class],
                ],
            ],
            [
                'key' => 'songs',
                'label' => 'Songs',
                'group' => 'Content Management',
                'icon' => 'heroicon-o-musical-note',
                'sort' => 2,
                'tabs' => [
                    ['label' => 'Songs', 'target' => SongResource::class],
                    ['label' => 'Song Categories', 'target' => SongCategoryResource::class],
                ],
            ],
            [
                'key' => 'rehearsals',
                'label' => 'Rehearsals',
                'group' => 'Content Management',
                'icon' => 'heroicon-o-musical-note',
                'sort' => 3,
                'tabs' => [
                    ['label' => 'Rehearsals', 'target' => RehearsalResource::class],
                    ['label' => 'Rehearsal Attendance', 'target' => RehearsalAttendanceResource::class],
                ],
            ],
            [
                'key' => 'media',
                'label' => 'Media',
                'group' => 'Content Management',
                'icon' => 'heroicon-o-photo',
                'sort' => 4,
                'tabs' => [
                    ['label' => 'Media', 'target' => MediaResource::class],
                    ['label' => 'Media Categories', 'target' => MediaCategoryResource::class],
                ],
            ],
            [
                'key' => 'events',
                'label' => 'Events',
                'group' => 'Content Management',
                'icon' => 'heroicon-o-calendar-days',
                'sort' => 5,
                'tabs' => [
                    ['label' => 'Events', 'target' => EventResource::class],
                    ['label' => 'Event Registrations', 'target' => EventRegistrationResource::class],
                ],
            ],
            [
                'key' => 'beneficiaries',
                'label' => 'Beneficiaries',
                'group' => 'Charity Management',
                'icon' => 'heroicon-o-users',
                'sort' => 1,
                'tabs' => [
                    ['label' => 'Beneficiaries', 'target' => BeneficiaryResource::class],
                    ['label' => 'Aid Distributions', 'target' => AidDistributionResource::class],
                ],
            ],
            [
                'key' => 'charity-reports',
                'label' => 'Charity reports',
                'group' => 'Charity Management',
                'icon' => 'heroicon-o-document-chart-bar',
                'sort' => 2,
                'tabs' => [
                    ['label' => 'Charity Report', 'target' => CharityReport::class],
                    ['label' => 'Beneficiary Report', 'target' => BeneficiaryReportPage::class],
                ],
            ],
            [
                'key' => 'tours',
                'label' => 'Tours',
                'group' => 'Tour Management',
                'icon' => 'heroicon-o-map',
                'sort' => 1,
                'tabs' => [
                    ['label' => 'Tours', 'target' => TourResource::class],
                    ['label' => 'Tour Passengers', 'target' => TourPassengerResource::class],
                ],
            ],
            [
                'key' => 'inventory',
                'label' => 'Inventory',
                'group' => 'Inventory Management',
                'icon' => 'heroicon-o-archive-box',
                'sort' => 1,
                'tabs' => [
                    ['label' => 'Inventory Items', 'target' => InventoryResource::class],
                    ['label' => 'Stock Movements', 'target' => StockMovementResource::class],
                    ['label' => 'Loss/Damage Records', 'target' => LossRecordResource::class],
                ],
            ],
            [
                'key' => 'facilities',
                'label' => 'Facilities & bookings',
                'group' => 'Operations',
                'icon' => 'heroicon-o-building-office',
                'sort' => 30,
                'tabs' => [
                    ['label' => 'Bookings', 'target' => BookingResource::class],
                    ['label' => 'Facilities', 'target' => FacilityResource::class],
                ],
            ],
        ];
    }

    /**
     * @return list<NavigationItem>
     */
    public static function navigationItems(): array
    {
        $items = [];

        foreach (static::hubs() as $hub) {
            $items[] = NavigationItem::make($hub['label'])
                ->group($hub['group'])
                ->icon($hub['icon'])
                ->sort($hub['sort'])
                ->visible(fn (): bool => (! RoleGate::is('student') || in_array($hub['key'], ['library', 'site-notices'], true))
                    && static::accessibleTabs($hub) !== [])
                ->url(fn (): string => static::hubUrl($hub['key']))
                ->isActiveWhen(fn (): bool => static::hubIsActive($hub['key']));
        }

        return $items;
    }

    /**
     * @return array{key: string, label: string, group: string, icon: string, sort: int, tabs: list<array{label: string, target: class-string}>}|null
     */
    public static function hubUrl(string $key, ?string $tab = null): string
    {
        $parameters = ['hub' => $key];

        if (is_string($tab) && $tab !== '') {
            $parameters['tab'] = $tab;
        }

        return ResourceTabHub::getUrl($parameters);
    }

    public static function hubUrlForTarget(string $target): ?string
    {
        foreach (static::hubs() as $hub) {
            foreach ($hub['tabs'] as $tab) {
                if ($tab['target'] === $target) {
                    return static::hubUrl($hub['key'], static::tabKey($target));
                }
            }
        }

        return null;
    }

    public static function hub(string $key): ?array
    {
        foreach (static::hubs() as $hub) {
            if ($hub['key'] === $key) {
                return $hub;
            }
        }

        return null;
    }

    public static function tabKey(string $target): string
    {
        return (string) str(class_basename($target))
            ->beforeLast('Resource')
            ->beforeLast('Page')
            ->kebab();
    }

    /**
     * @return list<array{key: string, label: string, target: class-string, type: string}>
     */
    public static function accessibleTabsForHub(string $key): array
    {
        $hub = static::hub($key);
        if ($hub === null) {
            return [];
        }

        $tabs = [];

        foreach ($hub['tabs'] as $tab) {
            if (! static::canAccessTarget($tab['target'])) {
                continue;
            }

            $tabs[] = [
                'key' => static::tabKey($tab['target']),
                'label' => $tab['label'],
                'target' => $tab['target'],
                'type' => is_subclass_of($tab['target'], Resource::class) ? 'resource' : 'page',
            ];
        }

        return $tabs;
    }

    public static function firstAccessibleHubKey(): ?string
    {
        foreach (static::hubs() as $hub) {
            if (static::accessibleTabsForHub($hub['key']) !== []) {
                return $hub['key'];
            }
        }

        return null;
    }

    public static function userCanAccessAnyHub(): bool
    {
        return static::firstAccessibleHubKey() !== null;
    }

    /**
     * @param  array{key: string, tabs: list<array{label: string, target: class-string}>}  $hub
     * @return list<array{label: string, url: string, active: bool}>
     */
    protected static function accessibleTabs(array $hub): array
    {
        return static::accessibleTabsForHub($hub['key']);
    }

    public static function hubIsActive(string $key): bool
    {
        if (request()->routeIs('filament.admin.pages.hubs') && request()->query('hub') === $key) {
            return true;
        }

        $hub = static::hub($key);
        if ($hub === null) {
            return false;
        }

        foreach ($hub['tabs'] as $tab) {
            $url = static::urlForTarget($tab['target']);
            if ($url && static::requestMatches($url)) {
                return true;
            }
        }

        return false;
    }

    protected static function canAccessTarget(string $target): bool
    {
        try {
            if (is_subclass_of($target, Resource::class)) {
                return $target::canViewAny();
            }

            if (is_subclass_of($target, Page::class)) {
                return $target::canAccess();
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    protected static function urlForTarget(string $target): ?string
    {
        try {
            if (is_subclass_of($target, Resource::class)) {
                return $target::getUrl('index');
            }

            if (is_subclass_of($target, Page::class)) {
                return $target::getUrl();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    protected static function requestMatches(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return false;
        }

        return request()->is($path) || request()->is($path.'/*');
    }
}
