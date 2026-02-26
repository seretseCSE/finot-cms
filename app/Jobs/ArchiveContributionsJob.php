<?php

namespace App\Jobs;

use App\Models\AcademicYear;
use App\Models\Contribution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveContributionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public int $academicYearId
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $academicYear = AcademicYear::find($this->academicYearId);
        
        if (!$academicYear) {
            Log::error('ArchiveContributionsJob: Academic Year not found', [
                'academic_year_id' => $this->academicYearId,
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($academicYear) {
                $contributionsCount = Contribution::where('academic_year_id', $academicYear->id)
                    ->where('is_archived', false)
                    ->update([
                        'is_archived' => true,
                        'archived_at' => now(),
                    ]);

                Log::info('Contributions archived successfully', [
                    'academic_year_id' => $academicYear->id,
                    'academic_year_name' => $academicYear->name,
                    'contributions_count' => $contributionsCount,
                    'archived_at' => now()->toDateTimeString(),
                ]);

                // Log to Tier-2 audit trail
                Log::channel('audit')->warning('Tier 2 Audit Log', [
                    'tier' => 2,
                    'action' => 'contributions_archived',
                    'academic_year_id' => $academicYear->id,
                    'academic_year_name' => $academicYear->name,
                    'contributions_count' => $contributionsCount,
                    'performed_by' => 'system',
                    'timestamp' => now()->toDateTimeString(),
                ]);

                // Send notification to finance_head (would need notification system)
                // $this->sendNotificationToFinanceHead($academicYear, $contributionsCount);
            });

        } catch (\Exception $e) {
            Log::error('ArchiveContributionsJob failed', [
                'academic_year_id' => $this->academicYearId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ArchiveContributionsJob failed permanently', [
            'academic_year_id' => $this->academicYearId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    private function sendNotificationToFinanceHead(AcademicYear $academicYear, int $contributionsCount): void
    {
        // Implementation would depend on your notification system
        // Example:
        // $financeHeads = User::role('finance_head')->get();
        // Notification::send($financeHeads, new ContributionsArchivedNotification($academicYear, $contributionsCount));
        
        Log::info('Notification would be sent to finance_head', [
            'academic_year' => $academicYear->name,
            'contributions_count' => $contributionsCount,
        ]);
    }
}
