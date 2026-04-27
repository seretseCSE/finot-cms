<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\BlogPost;
use App\Models\Contribution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SystemAutoArchiveCommand extends Command
{
    protected $signature = 'system:auto-archive';

    protected $description = 'Auto-archive old contributions, blog posts, and announcements based on retention policies';

    public function handle(): int
    {
        $this->info('Starting system auto-archive process...');

        $totalArchived = 0;

        // Archive contributions older than 3 years
        $totalArchived += $this->archiveContributions();

        // Archive blog posts older than 2 years
        $totalArchived += $this->archiveBlogPosts();

        // Archive announcements older than 1 year
        $totalArchived += $this->archiveAnnouncements();

        $this->info("✅ Auto-archive completed. Total records archived: {$totalArchived}");

        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'system_auto_archive',
            'entity_type' => 'system',
            'old_value' => null,
            'new_value' => json_encode(['total_archived' => $totalArchived]),
            'user_id' => 'system',
            'timestamp' => now()->toDateTimeString(),
        ]);

        return Command::SUCCESS;
    }

    private function archiveContributions(): int
    {
        $cutoffDate = now()->subYears(3);

        $contributions = Contribution::query()
            ->where('is_archived', false)
            ->where('created_at', '<', $cutoffDate)
            ->get();

        $count = 0;
        foreach ($contributions as $contribution) {
            $contribution->update([
                'is_archived' => true,
                'archived_at' => now(),
            ]);
            $count++;
        }

        if ($count > 0) {
            $this->info("✅ Archived {$count} contributions older than 3 years");
        }

        return $count;
    }

    private function archiveBlogPosts(): int
    {
        $cutoffDate = now()->subYears(2);

        $posts = BlogPost::query()
            ->where('status', '!=', 'Archived')
            ->where('created_at', '<', $cutoffDate)
            ->get();

        $count = 0;
        foreach ($posts as $post) {
            $post->update(['status' => 'Archived']);
            $count++;
        }

        if ($count > 0) {
            $this->info("✅ Archived {$count} blog posts older than 2 years");
        }

        return $count;
    }

    private function archiveAnnouncements(): int
    {
        $cutoffDate = now()->subYear();

        $announcements = Announcement::query()
            ->where('status', '!=', 'Archived')
            ->where('created_at', '<', $cutoffDate)
            ->get();

        $count = 0;
        foreach ($announcements as $announcement) {
            $announcement->update(['status' => 'Archived']);
            $count++;
        }

        if ($count > 0) {
            $this->info("✅ Archived {$count} announcements older than 1 year");
        }

        return $count;
    }
}
