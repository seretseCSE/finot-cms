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

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Update Contribution Amount')
                ->submit(null),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit Contribution Amount';
    }

    public function getSubheading(): string
    {
        return 'Update contribution amount settings for ' . $this->record->group->name . ' - ' . $this->record->month_name;
    }
}

