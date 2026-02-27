<?php

namespace App\Observers;

use App\Models\AcademicYear;
use App\Jobs\ArchiveContributionsJob;
use Illuminate\Support\Facades\Log;

class AcademicYearObserver
{
    /**
     * Handle the AcademicYear "updated" event.
     */
    public function updated(AcademicYear $academicYear): void
    {
        // Check if status changed to 'Inactive' (deactivation)
        if ($academicYear->wasChanged('status') && $academicYear->status === 'Inactive') {
            
            // Dispatch archival job to background queue
            ArchiveContributionsJob::dispatch($academicYear->id);
            
            Log::info('Academic Year deactivated - archival job dispatched', [
                'academic_year_id' => $academicYear->id,
                'academic_year_name' => $academicYear->name,
                'status' => $academicYear->status,
            ]);
        }
    }

    /**
     * Handle the AcademicYear "deleted" event.
     */
    public function deleted(AcademicYear $academicYear): void
    {
        // Archive contributions if academic year is deleted and was not active
        if ($academicYear->status !== 'Active') {
            ArchiveContributionsJob::dispatch($academicYear->id);
            
            Log::info('Academic Year deleted - archival job dispatched', [
                'academic_year_id' => $academicYear->id,
                'academic_year_name' => $academicYear->name,
            ]);
        }
    }
}
