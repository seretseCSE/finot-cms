<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\Log;

class AcademicYearService
{
    /**
     * Handle status change for academic year.
     *
     * @param AcademicYear $year The academic year
     * @param string $newStatus The new status
     * @return void
     */
    public function handleStatusChange(AcademicYear $year, string $newStatus): void
    {
        $oldStatus = $year->status;

        Log::info('EditAcademicYear - Status change: ' . $oldStatus . ' -> ' . $newStatus);

        // Check if status is being changed to Active
        if ($oldStatus !== 'Active' && $newStatus === 'Active') {
            Log::info('EditAcademicYear - Will call ensureSingleActiveYear after save');
            $this->ensureSingleActiveYear($year);
        }
    }

    /**
     * Ensure only one academic year is active.
     *
     * @param AcademicYear $activeYear The newly active academic year
     * @return void
     */
    public function ensureSingleActiveYear(AcademicYear $activeYear): void
    {
        Log::info('AcademicYearService - Calling ensureSingleActiveYear for record: ' . $activeYear->id);

        // Deactivate all other academic years
        AcademicYear::where('id', '!=', $activeYear->id)
            ->where('status', 'Active')
            ->update(['status' => 'Inactive']);
    }

    /**
     * Process data before creating academic year.
     *
     * @param array $data The form data
     * @param int $userId The user ID
     * @return array The modified data
     */
    public function processBeforeCreate(array $data, int $userId): array
    {
        $data['created_by'] = $userId;

        Log::info('CreateAcademicYear - Status being set to: ' . ($data['status'] ?? 'not set'));

        // Set default status to Draft since the field is disabled in form
        $data['status'] = $data['status'] ?? 'Draft';

        return $data;
    }
}
