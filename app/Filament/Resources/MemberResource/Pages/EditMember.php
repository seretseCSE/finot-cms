<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Forms\Components\CustomOptionSelect;
use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['parent_guardian_info'] = $this->record->parentGuardians->map(function ($pg) {
            return [
                'parent_id' => $pg->parent_id,
                'parent_name' => $pg->parent_name,
                'relationship' => $pg->relationship,
                'parent_phone' => $pg->phone,
            ];
        })->toArray();
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Process parent/guardian data directly
        if (isset($this->data['parent_guardian_info']) && is_array($this->data['parent_guardian_info'])) {
            // Remove existing relationships to avoid duplicates on edit
            \App\Models\MemberParentGuardian::where('member_id', $this->record->id)->delete();

            foreach ($this->data['parent_guardian_info'] as $parentData) {
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

                        // Update member count for new parent AFTER linkage
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

        Log::channel('audit')->info('Member Updated', [
            'member_id'   => $this->record->id,
            'member_code' => $this->record->member_code,
            'member_name' => $this->record->full_name,
            'updated_by'  => auth()->id(),
            'timestamp'   => now()->toDateTimeString(),
        ]);
    }
}