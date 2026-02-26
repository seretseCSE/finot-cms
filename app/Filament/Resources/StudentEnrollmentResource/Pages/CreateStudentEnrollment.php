<?php

namespace App\Filament\Resources\StudentEnrollmentResource\Pages;

use App\Filament\Resources\StudentEnrollmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateStudentEnrollment extends CreateRecord
{
    protected static string $resource = StudentEnrollmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['enrolled_by'] = Auth::id();
        $data['status'] = 'Enrolled';
        $data['enrolled_date'] = $data['enrolled_date'] ?? now()->toDateString();

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        Log::channel('audit')->warning('Tier 2 Audit Log', [
            'tier' => 2,
            'action' => 'enrolled',
            'entity' => 'student_enrollment',
            'enrollment_id' => $record->getKey(),
            'new_value' => [
                'member_id' => $record->member_id,
                'class_id' => $record->class_id,
                'academic_year_id' => $record->academic_year_id,
            ],
            'performed_by' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

