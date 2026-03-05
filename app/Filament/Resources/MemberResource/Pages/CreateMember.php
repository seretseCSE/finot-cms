<?php

namespace App\Filament\Resources\MemberResource\Pages;

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
            $memberCode = 'M-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
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

        // Process parent/guardian data directly
        if (isset($this->data['parent_guardian_info']) && is_array($this->data['parent_guardian_info'])) {
            foreach ($this->data['parent_guardian_info'] as $parentData) {
                \Log::info('Processing parent data', ['parentData' => $parentData]);
                
                if (empty($parentData['parent_name'])) {
                    continue;
                }

                // Check if parent_id is provided (existing parent)
                if (!empty($parentData['parent_id'])) {
                    // Link to existing parent
                    \App\Models\MemberParentGuardian::create([
                        'member_id' => $this->record->id,
                        'parent_id' => $parentData['parent_id'],
                        'parent_name' => $parentData['parent_name'],
                        'relationship' => $parentData['relationship'] ?? 'Guardian',
                        'phone' => $parentData['parent_phone'] ?? '',
                        'is_external' => false,
                    ]);

                    // Update member count for the existing parent
                    $parent = \App\Models\ParentModel::find($parentData['parent_id']);
                    if ($parent) {
                        $parent->updateMemberCount();
                    }
                } else {
                    // Check if parent already exists by phone number
                    $existingParent = \App\Models\ParentModel::byPhone($parentData['parent_phone'] ?? '')->first();
                    
                    if ($existingParent) {
                        // Link to existing parent instead of creating duplicate
                        \App\Models\MemberParentGuardian::create([
                            'member_id' => $this->record->id,
                            'parent_id' => $existingParent->id,
                            'parent_name' => $parentData['parent_name'],
                            'relationship' => $parentData['relationship'] ?? 'Guardian',
                            'phone' => $parentData['parent_phone'] ?? '',
                            'is_external' => false,
                        ]);

                        // Update member count for existing parent
                        $existingParent->updateMemberCount();
                    } else {
                        // Create new parent in parents table
                        $parent = \App\Models\ParentModel::create([
                            'full_name' => $parentData['parent_name'],
                            'phone' => $parentData['parent_phone'] ?? '',
                            'relationship_type' => $parentData['relationship'] ?? 'Guardian',
                            'is_active' => true,
                        ]);

                        // Link to new parent
                        \App\Models\MemberParentGuardian::create([
                            'member_id' => $this->record->id,
                            'parent_id' => $parent->id,
                            'parent_name' => $parentData['parent_name'],
                            'relationship' => $parentData['relationship'] ?? 'Guardian',
                            'phone' => $parentData['parent_phone'] ?? '',
                            'is_external' => false,
                        ]);

                        // Update member count for the new parent AFTER linkage
                        $parent->updateMemberCount();
                    }
                }
            }
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