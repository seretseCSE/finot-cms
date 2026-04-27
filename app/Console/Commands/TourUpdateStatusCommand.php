<?php

namespace App\Console\Commands;

use App\Models\Tour;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TourUpdateStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tour:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update tour statuses based on tour dates and registration status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting tour status update...');

        $tours = Tour::whereNotIn('status', ['Cancelled', 'Completed'])->get();

        $updatedCount = 0;

        foreach ($tours as $tour) {
            $oldStatus = $tour->status;
            $tour->updateStatusIfNeeded();

            if ($tour->status !== $oldStatus) {
                $updatedCount++;
                $this->line("Tour #{$tour->id} ({$tour->place}): {$oldStatus} → {$tour->status}");

                // Log the status change
                Log::channel('audit')->info('Tour status auto-updated', [
                    'tier' => 2,
                    'action' => 'tour_status_auto_updated',
                    'entity_id' => $tour->id,
                    'entity_type' => 'tour',
                    'old_value' => json_encode(['status' => $oldStatus]),
                    'new_value' => json_encode(['status' => $tour->status]),
                    'tour_date' => $tour->tour_date?->format('Y-m-d'),
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }
        }

        $this->info("Tour status update completed. Updated {$updatedCount} tours.");

        return Command::SUCCESS;
    }
}
