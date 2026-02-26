<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Contribution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveContributionsCommand extends Command
{
    protected $signature = 'contributions:archive {academic_year_id : The ID of the academic year to archive}';
    protected $description = 'Archive contributions for a specific academic year';

    public function handle(): int
    {
        $academicYearId = $this->argument('academic_year_id');
        
        $academicYear = AcademicYear::find($academicYearId);
        
        if (!$academicYear) {
            $this->error("Academic Year with ID {$academicYearId} not found.");
            return 1;
        }

        if ($academicYear->is_active) {
            $this->error("Cannot archive contributions for an active academic year. Deactivate the year first.");
            return 1;
        }

        $this->info("Archiving contributions for academic year: {$academicYear->name}");

        try {
            DB::transaction(function () use ($academicYear) {
                $contributionsCount = Contribution::where('academic_year_id', $academicYear->id)
                    ->where('is_archived', false)
                    ->update([
                        'is_archived' => true,
                        'archived_at' => now(),
                    ]);

                $this->info("Archived {$contributionsCount} contributions for {$academicYear->name}");

                // Log to Tier-2 audit trail
                Log::channel('audit')->warning('Tier 2 Audit Log', [
                    'tier' => 2,
                    'action' => 'contributions_archived',
                    'academic_year_id' => $academicYear->id,
                    'academic_year_name' => $academicYear->name,
                    'contributions_count' => $contributionsCount,
                    'performed_by' => 1, // System user
                    'timestamp' => now()->toDateTimeString(),
                ]);

                // Send notification to finance_head (would need to implement notification system)
                // Notification::send(User::role('finance_head')->get(), new ContributionsArchivedNotification($academicYear, $contributionsCount));
            });

            $this->info("✅ Contributions archived successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to archive contributions: " . $e->getMessage());
            Log::error('Contributions archival failed', [
                'academic_year_id' => $academicYearId,
                'error' => $e->getMessage(),
            ]);
            return 1;
        }
    }
}
