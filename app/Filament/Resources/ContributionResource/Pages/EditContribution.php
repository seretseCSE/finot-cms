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
                ->label('Update Contribution')
                ->submit(null),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit Contribution';
    }

    public function getSubheading(): string
    {
        return 'Update contribution details for ' . $this->record->member->full_name . ' - ' . $this->record->month_name;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure recorded_by remains the original user
        unset($data['recorded_by']);

        return $data;
    }
}

