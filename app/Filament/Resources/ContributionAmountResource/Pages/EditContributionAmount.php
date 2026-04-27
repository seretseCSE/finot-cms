<?php

namespace App\Filament\Resources\ContributionAmountResource\Pages;

use App\Filament\Resources\ContributionAmountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContributionAmount extends EditRecord
{
    protected static string $resource = ContributionAmountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => ContributionAmountResource::canDelete($this->record)),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit Contribution Amount';
    }

    public function getSubheading(): string
    {
        return 'Update contribution amount settings for '.$this->record->group->name.' - '.$this->record->month_name;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['group_id'] = is_array($data['group_id']) ? (\count($data['group_id']) > 0 ? $data['group_id'][0] : $data['group_id']) : $data['group_id'];
        $data['month_name'] = is_array($data['month_name']) ? (\count($data['month_name']) > 0 ? $data['month_name'][0] : $data['month_name']) : $data['month_name'];

        return $data;
    }
}
