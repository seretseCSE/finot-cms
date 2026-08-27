<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Charts\AttendanceTrendChart;
use App\Filament\Widgets\Charts\BeneficiaryByStatusChart;
use App\Filament\Widgets\Charts\BeneficiaryByTypeChart;
use App\Filament\Widgets\Charts\EnrollmentTrendChart;
use App\Filament\Widgets\Charts\ExpenseBreakdownChart;
use App\Filament\Widgets\Charts\GenderDistributionChart;
use App\Filament\Widgets\Charts\InventoryByCategoryChart;
use App\Filament\Widgets\Charts\MediaByCategoryChart;
use App\Filament\Widgets\Charts\MembersByGroupChart;
use App\Filament\Widgets\Charts\MemberTypeChart;
use App\Filament\Widgets\Charts\RehearsalAttendanceChart;
use App\Filament\Widgets\Charts\RevenueTrendChart;
use App\Filament\Widgets\Charts\SongsByCategoryChart;
use App\Filament\Widgets\Charts\TourStatusChart;
use App\Filament\Widgets\Charts\UserRegistrationChart;
use App\Filament\Widgets\Stats\ActiveBeneficiariesWidget;
use App\Filament\Widgets\Stats\ActiveEnrollmentsWidget;
use App\Filament\Widgets\Stats\ActiveMembersWidget;
use App\Filament\Widgets\Stats\ActiveSessionsWidget;
use App\Filament\Widgets\Stats\ActiveTeachersWidget;
use App\Filament\Widgets\Stats\AdultMembersWidget;
use App\Filament\Widgets\Stats\AidDistributedWidget;
use App\Filament\Widgets\Stats\AttendanceRateWidget;
use App\Filament\Widgets\Stats\BlogPostsWidget;
use App\Filament\Widgets\Stats\DeptAdultMembersWidget;
use App\Filament\Widgets\Stats\DeptKidsMembersWidget;
use App\Filament\Widgets\Stats\DeptTotalMembersWidget;
use App\Filament\Widgets\Stats\DeptYouthMembersWidget;
use App\Filament\Widgets\Stats\DepartmentMembersWidget;
use App\Filament\Widgets\Stats\FailedLoginsWidget;
use App\Filament\Widgets\Stats\KidsMembersWidget;
use App\Filament\Widgets\Stats\LowStockItemsWidget;
use App\Filament\Widgets\Stats\MonthlyContributionWidget;
use App\Filament\Widgets\Stats\NetPositionWidget;
use App\Filament\Widgets\Stats\PendingApprovalsWidget;
use App\Filament\Widgets\Stats\PublishedMediaWidget;
use App\Filament\Widgets\Stats\TotalExpensesWidget;
use App\Filament\Widgets\Stats\TotalIncomeWidget;
use App\Filament\Widgets\Stats\TotalInventoryItemsWidget;
use App\Filament\Widgets\Stats\TotalMembersWidget;
use App\Filament\Widgets\Stats\TotalRegisteredUsersWidget;
use App\Filament\Widgets\Stats\TourPassengersWidget;
use App\Filament\Widgets\Stats\UpcomingEventsWidget;
use App\Filament\Widgets\Stats\UpcomingRehearsalsWidget;
use App\Filament\Widgets\Stats\UpcomingToursWidget;
use App\Filament\Widgets\Stats\YouthMembersWidget;
use App\Filament\Widgets\Tables\PendingApprovalsTableWidget;
use App\Filament\Widgets\Tables\RecentAidDistributionsTable;
use App\Filament\Widgets\Tables\RecentAuditLogTable;
use App\Filament\Widgets\Tables\RecentContentTable;
use App\Filament\Widgets\Tables\RecentMembersTable;
use App\Filament\Widgets\Tables\RecentStockMovementsTable;
use App\Filament\Widgets\Tables\RecentTransactionsTable;
use App\Filament\Widgets\Tables\UpcomingToursScheduleTable;
use App\Filament\Widgets\OnboardingProgressWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -2;

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function getHeading(): string
    {
        return __('Welcome, :name', ['name' => \App\Support\RoleGate::user()?->name ?? __('there')]);
    }

    public function getWidgets(): array
    {
        $user = \App\Support\RoleGate::user();

        if (! $user) {
            return [];
        }

        if (\App\Support\RoleGate::is('superadmin')) {
            return [
                OnboardingProgressWidget::class,
                TotalRegisteredUsersWidget::class,
                ActiveSessionsWidget::class,
                FailedLoginsWidget::class,

                UserRegistrationChart::class,
                AttendanceTrendChart::class,

                RecentAuditLogTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('admin')) {
            return [
                OnboardingProgressWidget::class,
                TotalMembersWidget::class,
                KidsMembersWidget::class,
                YouthMembersWidget::class,
                AdultMembersWidget::class,

                TotalIncomeWidget::class,
                TotalExpensesWidget::class,
                MonthlyContributionWidget::class,

                RevenueTrendChart::class,
                ExpenseBreakdownChart::class,
                AttendanceTrendChart::class,

                RecentTransactionsTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('finance_head')) {
            return [
                TotalIncomeWidget::class,
                TotalExpensesWidget::class,
                NetPositionWidget::class,
                PendingApprovalsWidget::class,
                MonthlyContributionWidget::class,

                RevenueTrendChart::class,
                ExpenseBreakdownChart::class,

                RecentTransactionsTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('nibret_hisab_head')) {
            return [
                TotalIncomeWidget::class,
                TotalExpensesWidget::class,
                LowStockItemsWidget::class,
                TotalInventoryItemsWidget::class,

                RevenueTrendChart::class,
                ExpenseBreakdownChart::class,

                RecentStockMovementsTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('hr_head')) {
            return [
                TotalMembersWidget::class,
                ActiveMembersWidget::class,

                MemberTypeChart::class,
                GenderDistributionChart::class,

                RecentMembersTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('education_head')) {
            return [
                ActiveEnrollmentsWidget::class,
                ActiveTeachersWidget::class,
                AttendanceRateWidget::class,

                EnrollmentTrendChart::class,
                AttendanceTrendChart::class,
            ];
        }

        if (\App\Support\RoleGate::is('education_monitor')) {
            return [
                AttendanceRateWidget::class,

                AttendanceTrendChart::class,
            ];
        }

        if (\App\Support\RoleGate::is('tour_head')) {
            return [
                UpcomingToursWidget::class,
                TourPassengersWidget::class,

                TourStatusChart::class,

                UpcomingToursScheduleTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('charity_head')) {
            return [
                ActiveBeneficiariesWidget::class,
                AidDistributedWidget::class,

                BeneficiaryByTypeChart::class,
                BeneficiaryByStatusChart::class,

                RecentAidDistributionsTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('revenue_and_charity_head')) {
            return [
                UpcomingToursWidget::class,
                TourPassengersWidget::class,
                ActiveBeneficiariesWidget::class,
                AidDistributedWidget::class,

                TourStatusChart::class,
                BeneficiaryByTypeChart::class,
                BeneficiaryByStatusChart::class,

                UpcomingToursScheduleTable::class,
                RecentAidDistributionsTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('worship_monitor')) {
            return [
                UpcomingRehearsalsWidget::class,

                SongsByCategoryChart::class,
            ];
        }

        if (\App\Support\RoleGate::is('mezmur_head')) {
            return [
                UpcomingRehearsalsWidget::class,

                SongsByCategoryChart::class,
                RehearsalAttendanceChart::class,
            ];
        }

        if (\App\Support\RoleGate::is('av_head')) {
            return [
                PublishedMediaWidget::class,
                BlogPostsWidget::class,

                MediaByCategoryChart::class,

                RecentContentTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('inventory_staff')) {
            return [
                TotalInventoryItemsWidget::class,
                LowStockItemsWidget::class,

                InventoryByCategoryChart::class,

                RecentStockMovementsTable::class,
            ];
        }

        if (\App\Support\RoleGate::is('internal_relations_head')) {
            return [
                TotalMembersWidget::class,
                ActiveMembersWidget::class,

                MembersByGroupChart::class,

                RecentMembersTable::class,
            ];
        }

        return [];
    }

    public function getColumns(): int | array
    {
        return [
            'lg' => 4,
            'md' => 2,
            'sm' => 1,
        ];
    }
}
