<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class DashboardWidgetFactory
{
    /**
     * Get dashboard widgets based on user role.
     */
    public static function getWidgets(): array
    {
        $user = Auth::user();
        
        if (!$user) {
            return [];
        }

        return match ($user->roles->first()->name) {
            'superadmin' => self::getSuperadminWidgets(),
            'admin' => self::getAdminWidgets(),
            'hr_head' => self::getHrHeadWidgets(),
            'finance_head' => self::getFinanceHeadWidgets(),
            'nibret_hisab_head' => self::getNibretHisabHeadWidgets(),
            'inventory_staff' => self::getInventoryStaffWidgets(),
            'education_head' => self::getEducationHeadWidgets(),
            'education_monitor' => self::getEducationMonitorWidgets(),
            'worship_monitor' => self::getWorshipMonitorWidgets(),
            'mezmur_head' => self::getMezmurHeadWidgets(),
            'av_head' => self::getAvHeadWidgets(),
            'charity_head' => self::getCharityHeadWidgets(),
            'tour_head' => self::getTourHeadWidgets(),
            'department_secretary' => self::getDepartmentSecretaryWidgets(),
            'staff' => self::getStaffWidgets(),
            default => self::getDefaultWidgets(),
        };
    }

    /**
     * Get widgets for Superadmin role.
     */
    private static function getSuperadminWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'System Health',
                            'value' => self::getSystemHealthData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Users',
                        'value' => User::count(),
                        'icon' => 'heroicon-o-users',
                        'color' => 'success',
                    ],
                    [
                        'label' => 'Active Sessions',
                        'value' => self::getActiveSessionsCount(),
                        'icon' => 'heroicon-o-globe-americas',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Failed Logins (24h)',
                        'value' => self::getFailedLoginsCount(),
                        'icon' => 'heroicon-o-exclamation-triangle',
                        'color' => 'danger',
                    ],
                    [
                        'label' => 'Audit Log Summary',
                        'value' => self::getAuditLogSummary(),
                        'icon' => 'heroicon-o-document-text',
                        'color' => 'warning',
                    ],
                    [
                        'label' => 'Error Rate',
                        'value' => self::getErrorRate(),
                        'icon' => 'heroicon-o-bell',
                        'color' => 'info',
                    ],
                ],
                'description' => 'System overview and health metrics',
            ]),
        ];
    }

    /**
     * Get widgets for Admin role.
     */
    private static function getAdminWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Member Statistics',
                            'value' => self::getMemberStatisticsData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Members',
                        'value' => \App\Models\Member::count(),
                        'icon' => 'heroicon-o-users',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Pending Registrations',
                        'value' => self::getPendingRegistrationsCount(),
                        'icon' => 'heroicon-o-user-plus',
                        'color' => 'warning',
                    ],
                    [
                        'label' => 'Active Tours',
                        'value' => \App\Models\Tour::count(),
                        'icon' => 'heroicon-o-map',
                        'color' => 'success',
                    ],
                ],
                'description' => 'Member management and activity overview',
            ]),
            PendingCustomOptionsWidget::make([
                'count' => self::getPendingCustomOptionsCount(),
                'description' => 'System configuration options awaiting review',
            ]),
        ];
    }

    /**
     * Get widgets for HR Head role.
     */
    private static function getHrHeadWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Department Distribution',
                            'value' => self::getDepartmentDistributionData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Members',
                        'value' => \App\Models\Member::count(),
                        'icon' => 'heroicon-o-users',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Group Assignments',
                        'value' => \App\Models\GroupAssignment::count(),
                        'icon' => 'heroicon-o-users',
                        'color' => 'info',
                    ],
                    [
                        'label' => 'New Members (30 days)',
                        'value' => \App\Models\Member::where('created_at', '>=', now()->subDays(30))->count(),
                        'icon' => 'heroicon-o-user-plus',
                        'color' => 'success',
                    ],
                ],
                'description' => 'HR and member management overview',
            ]),
        ];
    }

    /**
     * Get widgets for Finance Head role.
     */
    private static function getFinanceHeadWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Monthly Contributions',
                            'value' => self::getMonthlyContributionsData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Contributions',
                        'value' => \App\Models\Contribution::sum('amount'),
                        'icon' => 'heroicon-o-currency-dollar',
                        'color' => 'success',
                    ],
                    [
                        'label' => 'This Month',
                        'value' => \App\Models\Contribution::whereMonth(now()->month)->whereYear(now()->year)->sum('amount'),
                        'icon' => 'heroicon-o-calendar',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Inventory Value',
                        'value' => \App\Models\InventoryItem::sum('unit_cost'),
                        'icon' => 'heroicon-o-cube',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Financial and inventory overview',
            ]),
        ];
    }

    /**
     * Get widgets for Nibret Hisab Head role.
     */
    private static function getNibretHisabHeadWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Financial Overview',
                            'value' => self::getFinancialOverviewData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Contributions',
                        'value' => \App\Models\Contribution::sum('amount'),
                        'icon' => 'heroicon-o-currency-dollar',
                        'color' => 'success',
                    ],
                    [
                        'label' => 'Total Donations',
                        'value' => \App\Models\Donation::sum('amount'),
                        'icon' => 'heroicon-o-gift',
                        'color' => 'warning',
                    ],
                    [
                        'label' => 'Inventory Items',
                        'value' => \App\Models\InventoryItem::count(),
                        'icon' => 'heroicon-o-cube',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Financial management and inventory tracking',
            ]),
        ];
    }

    /**
     * Get widgets for Inventory Staff role.
     */
    private static function getInventoryStaffWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Inventory Status',
                            'value' => self::getInventoryStatusData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Items',
                        'value' => \App\Models\InventoryItem::count(),
                        'icon' => 'heroicon-o-cube',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Low Stock Items',
                        'value' => \App\Models\InventoryItem::where('quantity', '<', 10)->count(),
                        'icon' => 'heroicon-o-exclamation-triangle',
                        'color' => 'warning',
                    ],
                    [
                        'label' => 'Recent Movements',
                        'value' => 'Last 7 days',
                        'icon' => 'heroicon-o-arrow-path',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Inventory management and tracking',
            ]),
        ];
    }

    /**
     * Get widgets for Education Head role.
     */
    private static function getEducationHeadWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Enrollment Trends',
                            'value' => self::getEnrollmentTrendsData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Students',
                        'value' => \App\Models\Enrollment::count(),
                        'icon' => 'heroicon-o-academic-cap',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Active Classes',
                        'value' => \App\Models\SchoolClass::where('is_active', true)->count(),
                        'icon' => 'heroicon-o-book-open',
                        'color' => 'success',
                    ],
                    [
                        'label' => 'Teachers',
                        'value' => \App\Models\Teacher::count(),
                        'icon' => 'heroicon-o-users',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Education system management overview',
            ]),
        ];
    }

    /**
     * Get widgets for Education Monitor role.
     */
    private static function getEducationMonitorWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Attendance Rate',
                            'value' => self::getAttendanceRateData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Today\'s Sessions',
                        'value' => \App\Models\AttendanceSession::whereDate('today')->count(),
                        'icon' => 'heroicon-o-calendar-check',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Pending Attendance',
                        'value' => \App\Models\AttendanceSession::where('is_locked', false)->count(),
                        'icon' => 'heroicon-o-clock',
                        'color' => 'warning',
                    ],
                ],
                'description' => 'Attendance monitoring and management',
            ]),
        ];
    }

    /**
     * Get widgets for Worship Monitor role.
     */
    private static function getWorshipMonitorWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Rehearsal Schedule',
                            'value' => self::getRehearsalScheduleData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Songs',
                        'value' => \App\Models\Song::count(),
                        'icon' => 'heroicon-o-musical-note',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Upcoming Rehearsals',
                        'value' => \App\Models\Rehearsal::where('rehearsal_date', '>=', now())->count(),
                        'icon' => 'heroicon-o-calendar',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Worship service monitoring',
            ]),
        ];
    }

    /**
     * Get widgets for Mezmur Head role.
     */
    private static function getMezmurHeadWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Song Library',
                            'value' => self::getSongLibraryData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Songs',
                        'value' => \App\Models\Song::count(),
                        'icon' => 'heroicon-o-musical-note',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Rehearsals This Month',
                        'value' => \App\Models\Rehearsal::whereMonth(now()->month)->whereYear(now()->year)->count(),
                        'icon' => 'heroicon-o-calendar',
                        'color' => 'success',
                    ],
                ],
                'description' => 'Worship management overview',
            ]),
        ];
    }

    /**
     * Get widgets for AV Head role.
     */
    private static function getAvHeadWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Media Uploads',
                            'value' => self::getMediaUploadsData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Media Files',
                        'value' => \App\Models\Document::count(),
                        'icon' => 'heroicon-o-photo',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Blog Posts',
                        'value' => 0, // TODO: Implement Blog model
                        'icon' => 'heroicono-document-text',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Media and content management overview',
            ]),
        ];
    }

    /**
     * Get widgets for Charity Head role.
     */
    private static function getCharityHeadWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Beneficiary Distribution',
                            'value' => self::getBeneficiaryDistributionData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Total Beneficiaries',
                        'value' => \App\Models\Beneficiary::count(),
                        'icon' => 'heroicon-o-heart',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Active Aid Programs',
                        'value' => 5, // TODO: Implement AidProgram model
                        'icon' => 'heroicon-o-hand-holding-heart',
                        'color' => 'success',
                    ],
                ],
                'description' => 'Charity and beneficiary management overview',
            ]),
        ];
    }

    /**
     * Get widgets for Tour Head role.
     */
    private static function getTourHeadWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Tour Registration Trends',
                            'value' => self::getTourRegistrationTrendsData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Active Tours',
                        'value' => \App\Models\Tour::where('is_active', true)->count(),
                        'icon' => 'heroicon-o-map',
                        'color' => 'success',
                    ],
                    [
                        'label' => 'Total Registrations',
                        'value' => \App\Models\Tour::count(),
                        'icon' => 'heroicon-o-user-plus',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Tour management and registration overview',
            ]),
        ];
    }

    /**
     * Get widgets for Department Secretary role.
     */
    private static function getDepartmentSecretaryWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Department Activity',
                            'value' => self::getDepartmentActivityData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Department Members',
                        'value' => \App\Models\Member::where('department_id', auth()->user()->department_id)->count(),
                        'icon' => 'heroicon-o-users',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'Department Documents',
                        'value' => \App\Models\Document::where('department_id', auth()->user()->department_id)->count(),
                        'icon' => 'heroicon-o-document',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Department-specific management',
            ]),
        ];
    }

    /**
     * Get widgets for Staff role.
     */
    private static function getStaffWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'Personal Activity',
                            'value' => self::getPersonalActivityData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'My Department',
                        'value' => auth()->user()->department?->name_en ?? 'No Department',
                        'icon' => 'heroicon-o-building-office',
                        'color' => 'primary',
                    ],
                    [
                        'label' => 'My Tasks',
                        'value' => 0, // TODO: Implement Task model
                        'icon' => 'heroicon-o-clipboard-check',
                        'color' => 'info',
                    ],
                ],
                'description' => 'Personal dashboard for staff members',
            ]),
        ];
    }

    /**
     * Get default widgets for other roles.
     */
    private static function getDefaultWidgets(): array
    {
        return [
            StatsOverviewWidget::make([
                'chart' => [
                    'chart' => [
                        'data' => [
                            'label' => 'System Overview',
                            'value' => self::getSystemOverviewData(),
                        ],
                    ],
                ],
                'stats' => [
                    [
                        'label' => 'Profile Completion',
                        'value' => self::getProfileCompletionPercentage(),
                        'icon' => 'heroicon-o-user',
                        'color' => 'info',
                    ],
                ],
                'description' => 'General dashboard overview',
            ]),
        ];
    }

    // Data helper methods
    private static function getSystemHealthData(): array
    {
        return [
            'label' => 'System Health',
            'data' => [85, 92, 78, 95], // Mock health scores
        ];
    }

    private static function getActiveSessionsCount(): int
    {
        return 150; // Mock data
    }

    private static function getFailedLoginsCount(): int
    {
        return 12; // Mock data
    }

    private static function getAuditLogSummary(): array
    {
        return [
            'label' => 'Recent Activity',
            'data' => [15, 8, 23], // Mock data
        ];
    }

    private static function getErrorRate(): float
    {
        return 2.5; // Mock data
    }

    private static function getMemberStatisticsData(): array
    {
        return [
            'label' => 'Member Growth',
            'data' => [120, 145, 167, 189], // Mock data
        ];
    }

    private static function getDepartmentDistributionData(): array
    {
        return [
            'label' => 'Department Distribution',
            'data' => [
                ['Internal Relations', 45],
                ['Nibret ena Hisab', 32],
                ['Education', 28],
                ['Revenue & Charity', 25],
                ['Mezmur', 20],
            ],
        ];
    }

    private static function getMonthlyContributionsData(): array
    {
        return [
            'label' => 'Monthly Contributions',
            'data' => [5000, 6500, 7200, 8100], // Mock data
        ];
    }

    private static function getFinancialOverviewData(): array
    {
        return [
            'label' => 'Financial Overview',
            'data' => [25000, 28000, 31000, 29000], // Mock data
        ];
    }

    private static function getInventoryStatusData(): array
    {
        return [
            'label' => 'Inventory Status',
            'data' => [
                ['In Stock', 45],
                ['Low Stock', 8],
                ['Out of Stock', 3],
            ],
        ];
    }

    private static function getEnrollmentTrendsData(): array
    {
        return [
            'label' => 'Enrollment Trends',
            'data' => [180, 195, 210, 225], // Mock data
        ];
    }

    private static function getAttendanceRateData(): array
    {
        return [
            'label' => 'Attendance Rate',
            'data' => [85, 88, 92, 87], // Mock data
        ];
    }

    private static function getRehearsalScheduleData(): array
    {
        return [
            'label' => 'Rehearsal Schedule',
            'data' => [
                ['Monday', 3],
                ['Wednesday', 2],
                ['Friday', 2],
            ],
        ];
    }

    private static function getSongLibraryData(): array
    {
        return [
            'label' => 'Song Library',
            'data' => [45, 52, 48, 61], // Mock data
        ];
    }

    private static function getBeneficiaryDistributionData(): array
    {
        return [
            'label' => 'Beneficiary Distribution',
            'data' => [
                ['Children', 120],
                ['Elderly', 45],
                ['Families', 67],
            ],
        ];
    }

    private static function getTourRegistrationTrendsData(): array
    {
        return [
            'label' => 'Tour Registration Trends',
            'data' => [25, 30, 45, 60], // Mock data
        ];
    }

    private static function getDepartmentActivityData(): array
    {
        return [
            'label' => 'Department Activity',
            'data' => [12, 18, 25, 30], // Mock data
        ];
    }

    private static function getPersonalActivityData(): array
    {
        return [
            'label' => 'Personal Activity',
            'data' => [8, 12, 15, 9], // Mock data
        ];
    }

    private static function getSystemOverviewData(): array
    {
        return [
            'label' => 'System Overview',
            'data' => [100, 85, 90], // Mock data
        ];
    }

    private static function getProfileCompletionPercentage(): int
    {
        return 75; // Mock data
    }

    private static function getPendingRegistrationsCount(): int
    {
        return 8; // Mock data
    }

    private static function getPendingCustomOptionsCount(): int
    {
        return 3; // Mock data
    }

    private static function getMediaUploadsData(): array
    {
        return [
            'label' => 'Media Uploads',
            'data' => [15, 22, 18, 25], // Mock data
        ];
    }
}

