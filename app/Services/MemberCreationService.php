<?php

namespace App\Services;

use App\Actions\Members\SyncParentGuardiansAction;
use App\Filament\Forms\Components\CustomOptionSelect;
use App\Models\Member;
use Illuminate\Support\Facades\Log;

class MemberCreationService
{
    /**
     * Process member data before creation
     */
    public function processBeforeCreate(array $data): array
    {
        $data['status'] = $data['status'] ?? 'Draft';
        $data['member_code'] = $data['member_code'] ?? $this->generateMemberCode();

        // Set department_id from current user if not provided
        if (!isset($data['department_id']) && auth()->user()->department_id) {
            $data['department_id'] = auth()->user()->department_id;
        }

        // Remove empty values but keep 0 and false
        $data = array_filter($data, fn ($v) => $v !== '' && $v !== null && $v !== []);

        return $data;
    }

    /**
     * Process after member creation
     */
    public function processAfterCreate($record, array $data): void
    {
        Log::info('afterCreate called', [
            'record_id' => $record->id,
            'data_keys' => array_keys($data),
            'has_parentGuardians' => isset($data['parent_guardian_info'])
        ]);

        // Process parent/guardian data via action
        if (isset($data['parent_guardian_info']) && is_array($data['parent_guardian_info'])) {
            app(SyncParentGuardiansAction::class)
                ->execute($record, $data['parent_guardian_info']);
        }

        // Record custom option usage now that record is actually saved
        CustomOptionSelect::saveUsageAndPending($data, [
            'title'             => 'title',
            'member_type'       => 'member_type',
            'member_status'     => 'status',
            'occupation_status' => 'occupation_status',
            'employment_status' => 'employment_status',
            'marital_status'    => 'marital_status',
        ]);

        Log::channel('audit')->info('Member Created', [
            'member_id'   => $record->id,
            'member_code' => $record->member_code,
            'member_name' => $record->full_name,
            'created_by'  => auth()->id(),
            'timestamp'   => now()->toDateTimeString(),
        ]);
    }

    /**
     * Generate unique member code
     */
    private function generateMemberCode(): string
    {
        do {
            $lastMember = Member::withTrashed()->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)->latest('id')->first();
            $nextId = $lastMember ? ($lastMember->id + 1) : 1;
            $memberCode = config('finot.member_code_prefix', 'M-') . str_pad($nextId, 6, '0', STR_PAD_LEFT);
        } while (Member::withTrashed()->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)->where('member_code', $memberCode)->exists());

        return $memberCode;
    }
}
