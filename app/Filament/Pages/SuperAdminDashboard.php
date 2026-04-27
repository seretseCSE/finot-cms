<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Donation;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboard extends Page
{
    protected static ?string $title = 'Super Admin Dashboard';

    protected static ?int $navigationSort = -1;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.super-admin-dashboard';
    }

    public static function canAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    public function getSystemStats(): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'active' => User::whereNotNull('last_login_at')->count(),
                'admin' => User::role('admin')->count(),
                'superadmin' => User::role('superadmin')->count(),
            ],
            'members' => [
                'total' => Member::count(),
                'active' => Member::where('status', 'Active')->count(),
                'new_this_month' => Member::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'financial' => [
                'donations_this_month' => Donation::where('created_at', '>=', now()->startOfMonth())->sum('amount'),
                'transactions_today' => FinancialTransaction::whereDate('created_at', today())->count(),
                'total_donations' => Donation::sum('amount'),
            ],
            'system' => [
                'audit_logs_today' => AuditLog::whereDate('created_at', today())->count(),
                'last_backup' => $this->getLastBackupTime(),
                'system_uptime' => $this->getSystemUptime(),
                'storage_usage' => $this->getStorageUsage(),
            ],
        ];
    }

    public function getRecentActivity(): array
    {
        return [
            'recent_logins' => User::orderBy('last_login_at', 'desc')
                ->whereNotNull('last_login_at')
                ->limit(5)
                ->get(['name', 'email', 'last_login_at']),

            'recent_audit_logs' => AuditLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),

            'critical_errors' => DB::table('error_logs')
                ->where(function ($query) {
                    $query->where('error_type', 'like', '%critical%')
                        ->orWhere('error_type', 'like', '%emergency%')
                        ->orWhere('error_type', 'like', '%alert%')
                        ->orWhere('error_type', 'like', '%error%');
                })
                ->where('created_at', '>=', now()->subHours(24))
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];
    }

    public function getDepartmentStats(): array
    {
        return [
            'hr' => [
                'members' => Member::count(),
                'staff' => User::whereHas('roles', fn ($query) => $query->whereIn('name', ['hr_head', 'hr_staff']))->count(),
            ],
            'finance' => [
                'donations' => Donation::where('created_at', '>=', now()->startOfMonth())->count(),
                'transactions' => FinancialTransaction::where('created_at', '>=', now()->startOfMonth())->count(),
                'staff' => User::role(['finance_head', 'nibret_hisab_head'])->count(),
            ],
            'education' => [
                'students' => DB::table('student_enrollments')->count(),
                'teachers' => DB::table('teachers')->count(),
                'classes' => DB::table('classes')->count(),
            ],
            'media' => [
                'announcements' => DB::table('announcements')->count(),
                'blog_posts' => DB::table('blog_posts')->count(),
                'media_files' => DB::table('media_items')->count(),
            ],
        ];
    }

    private function getLastBackupTime(): string
    {
        $backupPath = storage_path('app/backups');
        if (! is_dir($backupPath)) {
            return 'No backups found';
        }

        $files = glob($backupPath.'/*.zip');
        if (empty($files)) {
            return 'No backups found';
        }

        $latestFile = max($files);

        return Carbon::createFromTimestamp(filemtime($latestFile))->diffForHumans();
    }

    private function getSystemUptime(): string
    {
        // Simple uptime calculation - could be enhanced with actual server uptime
        return 'Unknown';
    }

    private function getStorageUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        $percentage = ($used / $total) * 100;

        return [
            'used' => $this->formatBytes($used),
            'total' => $this->formatBytes($total),
            'percentage' => round($percentage, 2),
        ];
    }

    private function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backup')
                ->label('Create Backup')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->createBackup()),

            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->refreshData()),
        ];
    }

    public function createBackup()
    {
        // Redirect to backup page or trigger backup
        $this->redirect('/admin/backup-restore');
    }

    public function refreshData()
    {
        $this->notify('success', 'Dashboard data refreshed');
    }
}
