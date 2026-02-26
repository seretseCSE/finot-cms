<?php

namespace App\Console\Commands;

use App\Models\MediaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MediaAutoArchiveCommand extends Command
{
    protected $signature = 'media:auto-archive';
    protected $description = 'Auto-archive media items older than 5 years';

    public function handle(): int
    {
        $this->info('Starting media auto-archival process...');

        try {
            // Find media items older than 5 years
            $cutoffDate = now()->subYears(5);
            
            $mediaItems = MediaItem::where('created_at', '<', $cutoffDate)
                ->whereNull('deleted_at')
                ->get();

            $archivedCount = 0;

            foreach ($mediaItems as $mediaItem) {
                $mediaItem->delete(); // Soft delete
                $archivedCount++;
            }

            $this->info("✅ Archived {$archivedCount} media items older than 5 years");

            // Log to audit trail
            Log::channel('audit')->info('Tier 1 Audit Log', [
                'tier' => 1,
                'action' => 'media_auto_archived',
                'entity_type' => 'media_items',
                'old_value' => null,
                'new_value' => json_encode([
                    'archived_count' => $archivedCount,
                    'cutoff_date' => $cutoffDate->toDateTimeString(),
                ]),
                'user_id' => 'system',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to archive media: " . $e->getMessage());
            Log::error('Media auto-archival failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
