<?php

namespace App\Filament\Widgets;

use App\Models\Announcement;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class GlobalAnnouncementWidget extends Widget
{
    protected static ?int $sort = 1;
    
    protected static ?string $heading = 'Global Announcements';
    
    public static function canView(): bool
    {
        return Auth::check();
    }

    protected function getViewPath(): string
    {
        return 'filament.widgets.global-announcement-widget';
    }

    /**
     * Get active global announcements for the current user
     */
    public function getActiveGlobalAnnouncements(): array
    {
        $user = Auth::user();
        
        return Announcement::query()
            ->where('is_global', true)
            ->where('status', 'Active')
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->where(function ($query) use ($user) {
                // Filter by target audience
                $query->where('target_audience', 'all_users')
                    ->orWhere(function ($q) use ($user) {
                        // Simple audience filtering for now
                        if ($user->hasRole(['admin', 'superadmin'])) {
                            $q->where('target_audience', 'admin_only');
                        }
                    });
            })
            ->orderBy('is_urgent', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Get announcement statistics for admin users
     */
    public function getAnnouncementStats(): array
    {
        if (!Auth::user()->hasRole(['admin', 'superadmin'])) {
            return [];
        }

        return [
            'total_global' => Announcement::where('is_global', true)->count(),
            'active_global' => Announcement::where('is_global', true)->where('status', 'Active')->count(),
            'urgent_global' => Announcement::where('is_global', true)->where('is_urgent', true)->where('status', 'Active')->count(),
            'total_users' => User::count(),
        ];
    }

    /**
     * Acknowledge announcement action
     */
    public function acknowledgeAnnouncement(int $announcementId): void
    {
        $announcement = Announcement::find($announcementId);
        
        if (!$announcement || !$announcement->isGlobalAnnouncement()) {
            return;
        }

        $announcement->acknowledgeBy(Auth::id());

        Notification::make()
            ->title('Announcement Acknowledged')
            ->body('You have successfully acknowledged this announcement.')
            ->success()
            ->send();
    }
}
