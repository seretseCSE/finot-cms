<?php

namespace App\Filament\Resources\TeacherAssignmentResource\Pages;

use App\Filament\Resources\TeacherAssignmentResource;
use App\Models\AcademicYear;
use Filament\Resources\Pages\CreateRecord;

class CreateTeacherAssignment extends CreateRecord
{
    protected static string $resource = TeacherAssignmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $activeAcademicYear = AcademicYear::where('status', 'Active')
            ->where('phase', 'current')
            ->first()
            ?? AcademicYear::where('status', 'Active')->orderBy('start_date', 'desc')->first();

        $data['academic_year_id'] = $activeAcademicYear?->id;

        return $data;
    }
}
