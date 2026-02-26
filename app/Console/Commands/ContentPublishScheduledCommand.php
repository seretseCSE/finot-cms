<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\Announcement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ContentPublishScheduledCommand extends Command
{
    protected $signature = 'content:publish-scheduled';
    protected $description = 'Publish scheduled blog posts and expire announcements';

    public function handle(): int
    {
        $this->info('Publishing scheduled content...');

        try {
            // Publish scheduled blog posts
            $scheduledPosts = BlogPost::where('status', 'Scheduled')
                ->where('publish_date', '<=', now())
                ->get();

            $publishedPostsCount = 0;
            foreach ($scheduledPosts as $post) {
                if ($post->shouldPublish()) {
                    $post->publish();
                    $publishedPostsCount++;
                }
            }

            $this->info("✅ Published {$publishedPostsCount} scheduled blog posts");

            // Expire announcements
            $expiredAnnouncements = Announcement::where('status', 'Active')
                ->where('end_date', '<', now())
                ->get();

            $expiredAnnouncementsCount = 0;
            foreach ($expiredAnnouncements as $announcement) {
                if ($announcement->shouldExpire()) {
                    $announcement->expire();
                    $expiredAnnouncementsCount++;
                }
            }

            $this->info("✅ Expired {$expiredAnnouncementsCount} announcements");

            // Log to audit trail
            Log::channel('audit')->info('Tier 1 Audit Log', [
                'tier' => 1,
                'action' => 'content_scheduler_run',
                'entity_type' => 'scheduled_content',
                'old_value' => null,
                'new_value' => json_encode([
                    'published_posts' => $publishedPostsCount,
                    'expired_announcements' => $expiredAnnouncementsCount,
                ]),
                'user_id' => 'system',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to publish scheduled content: " . $e->getMessage());
            Log::error('Content scheduler failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
