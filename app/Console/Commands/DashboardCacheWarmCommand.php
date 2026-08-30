<?php

namespace App\Console\Commands;

use App\Enums\MemberType;
use App\Models\AidDistribution;
use App\Models\Beneficiary;
use App\Models\BlogPost;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\InventoryItem;
use App\Models\MediaItem;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\PageView;
use App\Models\Rehearsal;
use App\Models\Song;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\Tour;
use App\Models\TourPassenger;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardCacheWarmCommand extends Command
{
    protected $signature = 'dashboard:cache-warm';

    protected $description = 'Pre-warm all dashboard widget caches for faster page loads';

    public function handle(): int
    {
        $this->info('Warming dashboard caches...');
        $start = microtime(true);

        $this->warmMembershipCaches();
        $this->warmFinanceCaches();
        $this->warmEducationCaches();
        $this->warmChartCaches();
        $this->warmMiscCaches();

        $elapsed = round((microtime(true) - $start) * 1000);
        $this->newLine();
        $this->info("Dashboard caches warmed in {$elapsed}ms.");

        return Command::SUCCESS;
    }

    protected function warmMembershipCaches(): void
    {
        $this->line('  Membership...');

        Cache::remember('dashboard_total_members', 300, fn () => Member::count());

        Cache::remember('dashboard_active_members', 300, function () {
            $count = Member::where('status', 'Active')->count();
            $total = Member::count();
            return ['count' => $count, 'rate' => $total > 0 ? round(($count / $total) * 100, 1) : 0];
        });

        Cache::remember('dashboard_kids_members', 300, fn () => Member::where('member_type', MemberType::KIDS)->count());
        Cache::remember('dashboard_youth_members', 300, fn () => Member::where('member_type', MemberType::YOUTH)->count());
        Cache::remember('dashboard_adult_members', 300, fn () => Member::where('member_type', MemberType::ADULT)->count());

        Cache::remember('dashboard_registered_users', 300, fn () => [
            'total' => User::count(),
            'active_today' => User::whereDate('last_login_at', today())->count(),
        ]);

        Cache::remember('dashboard_active_sessions', 60, fn () => DB::table('sessions')->count());
        Cache::remember('dashboard_failed_logins', 300, fn () => User::where('failed_login_attempts', '>', 0)->sum('failed_login_attempts'));
    }

    protected function warmFinanceCaches(): void
    {
        $this->line('  Finance...');
        $month = now()->format('Y-m');

        Cache::remember("dashboard_income_mtd_{$month}", 300, function () {
            $current = FinancialTransaction::income()->approved()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            $previous = FinancialTransaction::income()->approved()
                ->whereMonth('transaction_date', now()->subMonth()->month)
                ->whereYear('transaction_date', now()->subMonth()->year)
                ->sum('amount');

            $growth = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
            return ['current' => $current, 'growth' => $growth];
        });

        Cache::remember("dashboard_expenses_mtd_{$month}", 300, function () {
            $current = FinancialTransaction::expense()->approved()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            $previous = FinancialTransaction::expense()->approved()
                ->whereMonth('transaction_date', now()->subMonth()->month)
                ->whereYear('transaction_date', now()->subMonth()->year)
                ->sum('amount');

            $growth = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
            return ['current' => $current, 'growth' => $growth];
        });

        Cache::remember("dashboard_net_position_{$month}", 300, function () {
            $income = FinancialTransaction::income()->approved()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            $expenses = FinancialTransaction::expense()->approved()
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            return $income - $expenses;
        });

        Cache::remember("dashboard_contributions_mtd_{$month}", 300, function () {
            $total = Contribution::where('is_paid', true)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount');

            $count = Contribution::where('is_paid', true)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->distinct('member_id')
                ->count('member_id');

            return ['total' => $total, 'count' => $count];
        });

        Cache::remember('dashboard_pending_approvals', 60, fn () => FinancialTransaction::pending()->count());

        $ym = now()->format('Y_m');
        Cache::remember("dashboard_aid_distributed_{$ym}", 300, fn () =>
            AidDistribution::whereMonth('distribution_date', now()->month)
                ->whereYear('distribution_date', now()->year)
                ->sum('amount')
        );
    }

    protected function warmEducationCaches(): void
    {
        $this->line('  Education...');

        Cache::remember('dashboard_active_enrollments', 300, fn () =>
            StudentEnrollment::whereHas('academicYear', fn ($q) => $q->where('status', 'Active'))->count()
        );

        Cache::remember('dashboard_active_teachers', 300, fn () => Teacher::count());

        Cache::remember('dashboard_attendance_rate', 300, function () {
            $row = DB::table('student_attendances')
                ->join('attendance_sessions', 'student_attendances.session_id', '=', 'attendance_sessions.id')
                ->whereBetween('attendance_sessions.session_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN student_attendances.status = 'Present' THEN 1 ELSE 0 END) as present")
                ->first();

            return $row && $row->total > 0 ? round(($row->present / $row->total) * 100, 1) : 0;
        });

        Cache::remember('dashboard_attendance_trend', 300, function () {
            $startDate = now()->subWeeks(11)->startOfWeek();
            $rows = DB::table('student_attendances')
                ->join('attendance_sessions', 'student_attendances.session_id', '=', 'attendance_sessions.id')
                ->where('attendance_sessions.session_date', '>=', $startDate)
                ->selectRaw("YEARWEEK(attendance_sessions.session_date, 1) as yw, MIN(attendance_sessions.session_date) as week_start, COUNT(*) as total, SUM(CASE WHEN student_attendances.status = 'Present' THEN 1 ELSE 0 END) as present")
                ->groupBy('yw')
                ->orderBy('yw')
                ->get();

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = \Carbon\Carbon::parse($row->week_start)->format('M j');
                $data[] = $row->total > 0 ? round(($row->present / $row->total) * 100, 1) : 0;
            }

            return [
                'datasets' => [['label' => 'Attendance %', 'data' => $data, 'borderColor' => '#22c55e', 'backgroundColor' => '#22c55e']],
                'labels' => $labels,
            ];
        });
    }

    protected function warmChartCaches(): void
    {
        $this->line('  Charts...');

        Cache::remember('dashboard_revenue_trend', 300, function () {
            $incomeData = [];
            $expenseData = [];
            $labels = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $labels[] = $date->format('M Y');
                $incomeData[] = FinancialTransaction::income()->approved()
                    ->whereMonth('transaction_date', $date->month)
                    ->whereYear('transaction_date', $date->year)
                    ->sum('amount');
                $expenseData[] = FinancialTransaction::expense()->approved()
                    ->whereMonth('transaction_date', $date->month)
                    ->whereYear('transaction_date', $date->year)
                    ->sum('amount');
            }
            return [
                'datasets' => [
                    ['label' => 'Income', 'data' => $incomeData, 'borderColor' => '#22c55e', 'backgroundColor' => '#22c55e'],
                    ['label' => 'Expenses', 'data' => $expenseData, 'borderColor' => '#ef4444', 'backgroundColor' => '#ef4444'],
                ],
                'labels' => $labels,
            ];
        });

        Cache::remember('dashboard_expense_breakdown', 300, fn () =>
            FinancialTransaction::expense()
                ->whereYear('transaction_date', now()->year)
                ->selectRaw('COALESCE(category, "Uncategorized") as category, SUM(amount) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->toArray()
        );

        Cache::remember('dashboard_user_registration_chart', 300, function () {
            $data = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            return [
                'datasets' => [['label' => 'New Users', 'data' => $data->pluck('count')->toArray(), 'borderColor' => '#3b82f6', 'backgroundColor' => '#3b82f6', 'fill' => true]],
                'labels' => $data->pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M j'))->toArray(),
            ];
        });

        Cache::remember('dashboard_gender_distribution_chart', 300, fn () =>
            Member::selectRaw('gender, COUNT(*) as count')->groupBy('gender')->pluck('count', 'gender')->toArray()
        );

        Cache::remember('dashboard_member_type_chart', 300, fn () =>
            Member::selectRaw('member_type, COUNT(*) as count')->groupBy('member_type')->pluck('count', 'member_type')->toArray()
        );

        Cache::remember('dashboard_members_by_group_chart', 300, function () {
            $groups = MemberGroup::withCount('members')->orderByDesc('members_count')->limit(10)->get();
            return [
                'datasets' => [['label' => 'Members', 'data' => $groups->pluck('members_count')->toArray(), 'backgroundColor' => '#3b82f6']],
                'labels' => $groups->pluck('name')->toArray(),
            ];
        });

        Cache::remember('dashboard_enrollment_trend_chart', 300, function () {
            $data = StudentEnrollment::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(fn ($item) => ['label' => date('M Y', mktime(0, 0, 0, $item->month, 1, $item->year)), 'count' => $item->count]);
            return [
                'datasets' => [['label' => 'Enrollments', 'data' => $data->pluck('count')->toArray(), 'borderColor' => '#8b5cf6', 'backgroundColor' => '#8b5cf6', 'fill' => true]],
                'labels' => $data->pluck('label')->toArray(),
            ];
        });

        Cache::remember('dashboard_tour_status_chart', 300, fn () =>
            Tour::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status')->toArray()
        );

        Cache::remember('dashboard_beneficiary_type_chart', 300, fn () =>
            Beneficiary::selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type')->toArray()
        );

        Cache::remember('dashboard_beneficiary_status_chart', 300, fn () =>
            Beneficiary::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status')->toArray()
        );

        Cache::remember('dashboard_rehearsal_attendance', 300, function () {
            $rehearsals = Rehearsal::where('date_time', '>=', now()->subDays(30))
                ->orderBy('date_time')
                ->withCount(['attendance', 'attendance as present_count' => fn ($q) => $q->where('status', 'Present')])
                ->get();
            $labels = [];
            $data = [];
            foreach ($rehearsals as $rehearsal) {
                $labels[] = $rehearsal->date_time?->format('M j') ?? 'N/A';
                $data[] = $rehearsal->attendance_count > 0 ? round(($rehearsal->present_count / $rehearsal->attendance_count) * 100, 1) : 0;
            }
            return [
                'datasets' => [['label' => 'Attendance %', 'data' => $data, 'borderColor' => '#8b5cf6', 'backgroundColor' => '#8b5cf6']],
                'labels' => $labels,
            ];
        });

        Cache::remember('dashboard_songs_by_category_chart', 300, fn () =>
            Song::selectRaw('category_id, COUNT(*) as count')->groupBy('category_id')->with('category')->get()
                ->mapWithKeys(fn ($item) => [$item->category?->name ?? 'Uncategorized' => $item->count])->toArray()
        );

        Cache::remember('dashboard_inventory_by_category_chart', 300, fn () =>
            InventoryItem::selectRaw('category, COUNT(*) as count')->groupBy('category')->pluck('count', 'category')->toArray()
        );

        Cache::remember('dashboard_media_by_category_chart', 300, fn () =>
            MediaItem::selectRaw('category_id, COUNT(*) as count')->groupBy('category_id')->with('category')->get()
                ->mapWithKeys(fn ($item) => [$item->category?->name ?? 'Uncategorized' => $item->count])->toArray()
        );
    }

    protected function warmMiscCaches(): void
    {
        $this->line('  Misc...');

        Cache::remember('dashboard_upcoming_tours', 300, fn () =>
            Tour::where('tour_date', '>=', today())->whereIn('status', ['active', 'scheduled'])->count()
        );

        Cache::remember('dashboard_tour_passengers', 300, fn () =>
            TourPassenger::whereYear('created_at', now()->year)->count()
        );

        Cache::remember('dashboard_active_beneficiaries', 300, fn () =>
            Beneficiary::where('status', 'Active')->count()
        );

        Cache::remember('dashboard_upcoming_rehearsals', 300, fn () =>
            Rehearsal::where('date_time', '>=', today())->count()
        );

        Cache::remember('dashboard_upcoming_events', 300, fn () =>
            Event::where('date_time', '>=', today())->count()
        );

        Cache::remember('dashboard_total_songs', 300, fn () => Song::count());
        Cache::remember('dashboard_published_media', 300, fn () => MediaItem::count());
        Cache::remember('dashboard_total_inventory', 300, fn () => InventoryItem::count());
        Cache::remember('dashboard_low_stock_items', 300, fn () => InventoryItem::where('quantity', '<=', 5)->count());

        $ym = now()->format('Y_m');
        Cache::remember("dashboard_blog_posts_{$ym}", 300, fn () =>
            BlogPost::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count()
        );

        Cache::remember('dashboard_visitor_stats', 300, fn () => [
            'today' => PageView::query()->whereDate('created_at', today())->count(),
            'week' => PageView::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'unique' => PageView::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('count(distinct session_hash) as c')
                ->value('c'),
        ]);
    }
}
