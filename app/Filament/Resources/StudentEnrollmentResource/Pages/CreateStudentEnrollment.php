<?php

namespace App\Filament\Resources\StudentEnrollmentResource\Pages;

use App\Filament\Resources\StudentEnrollmentResource;
use App\Models\StudentEnrollment;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    protected function handleRecordCreation(array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($data): StudentEnrollment {
            $enrollment = StudentEnrollment::create([
                'member_id' => $data['member_id'],
                'class_id' => $data['class_id'],
                'academic_year_id' => $data['academic_year_id'],
                'enrolled_date' => $data['enrolled_date'],
                'enrolled_by' => $data['enrolled_by'],
                'status' => 'Enrolled',
            ]);

            Log::channel('audit')->warning('Tier 2 Audit Log', [
                'tier' => 2,
                'action' => 'enrolled',
                'entity' => 'student_enrollment',
                'enrollment_id' => $enrollment->getKey(),
                'new_value' => [
                    'member_id' => $enrollment->member_id,
                    'class_id' => $enrollment->class_id,
                    'academic_year_id' => $enrollment->academic_year_id,
                ],
                'performed_by' => Auth::id(),
                'timestamp' => now()->toDateTimeString(),
            ]);

            Notification::make()
                ->title('Student enrolled successfully')
                ->success()
                ->send();

            return $enrollment;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
