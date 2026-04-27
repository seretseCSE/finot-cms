<?php

namespace App\Filament\Resources\StudentEnrollmentResource\Pages;

use App\Filament\Resources\StudentEnrollmentResource;
use App\Models\Member;
use Filament\Resources\Pages\EditRecord;

class EditStudentEnrollment extends EditRecord
{
    protected static string $resource = StudentEnrollmentResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        // Set the group_id based on the member's current assignment
        if ($this->record->member_id) {
            $member = Member::withoutDepartmentScope()->find($this->record->member_id);
            $groupId = $member?->currentGroupAssignment?->group_id;

            if ($groupId) {
                // Get current form data and add group_id
                $currentData = $this->form->getState();
                $currentData['group_id'] = $groupId;
                $this->form->fill($currentData);
            }
        }
    }
}
