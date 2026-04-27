<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Actions\Members\SyncParentGuardiansAction;
use App\Filament\Forms\Components\CustomOptionSelect;
use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status']     = $data['status'] ?? 'Draft';
        $data['member_code'] = $data['member_code'] ?? $this->generateMemberCode();

        // Set department_id from current user if not provided
        if (!isset($data['department_id']) && auth()->user()->department_id) {
            $data['department_id'] = auth()->user()->department_id;
        }

        // Remove empty values but keep 0 and false
        $data = array_filter($data, fn ($v) => $v !== '' && $v !== null && $v !== []);

        return $data;
    }

    protected function generateMemberCode(): string
    {
        do {
            $lastMember = Member::withTrashed()->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)->latest('id')->first();
            $nextId = $lastMember ? ($lastMember->id + 1) : 1;
            $memberCode = config('finot.member_code_prefix', 'M-') . str_pad($nextId, 6, '0', STR_PAD_LEFT);
        } while (Member::withTrashed()->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)->where('member_code', $memberCode)->exists());

        return $memberCode;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        \Log::info('afterCreate called', [
            'record_id' => $this->record->id,
            'data_keys' => array_keys($this->data),
            'has_parentGuardians' => isset($this->data['parent_guardian_info'])
        ]);

        // Process parent/guardian data via action
        if (isset($this->data['parent_guardian_info']) && is_array($this->data['parent_guardian_info'])) {
            app(SyncParentGuardiansAction::class)
                ->execute($this->record, $this->data['parent_guardian_info']);
        }

        // Record custom option usage now that the record is actually saved
        CustomOptionSelect::saveUsageAndPending($this->data, [
            'title'             => 'title',
            'member_type'       => 'member_type',
            'member_status'     => 'status',
            'occupation_status' => 'occupation_status',
            'employment_status' => 'employment_status',
            'marital_status'    => 'marital_status',
        ]);

        Log::channel('audit')->info('Member Created', [
            'member_id'   => $this->record->id,
            'member_code' => $this->record->member_code,
            'member_name' => $this->record->full_name,
            'created_by'  => auth()->id(),
            'timestamp'   => now()->toDateTimeString(),
        ]);
    }
}
