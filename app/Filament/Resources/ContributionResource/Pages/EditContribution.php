<?php

namespace App\Filament\Resources\ContributionResource\Pages;

use App\Filament\Resources\ContributionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContribution extends EditRecord
{
    protected static string $resource = ContributionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => ContributionResource::canDelete($this->record)),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Update Contribution'),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit Contribution';
    }

    public function getSubheading(): string
    {
        return 'Update contribution details for '.$this->record->member?->first_name;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['recorded_by']);
        $data['is_paid'] = $data['status'] === 'Paid';

        return $data;
    }
}
