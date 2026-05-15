<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Actions\Members\SyncParentGuardiansAction;
use App\Filament\Forms\Components\CustomOptionSelect;
use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Actions\Action;
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
        // Process parent/guardian data via action
        if (isset($this->data['parent_guardian_info']) && is_array($this->data['parent_guardian_info'])) {
            app(SyncParentGuardiansAction::class)
                ->execute($this->record, $this->data['parent_guardian_info'], replaceExisting: true);
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

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save Changes')
                ->icon('heroicon-o-check'),

            $this->getCancelFormAction(),

            Action::make('previous_tab')
                ->label('Previous')
                ->icon('heroicon-o-chevron-left')
                ->color('gray')
                ->extraAttributes(['class' => 'member-tab-previous ms-auto'])
                ->action(function () {
                }),

            Action::make('next_tab')
                ->label('Next')
                ->icon('heroicon-o-chevron-right')
                ->color('primary')
                ->extraAttributes(['class' => 'member-tab-next'])
                ->action(function () {
                }),
        ];
    }
}
