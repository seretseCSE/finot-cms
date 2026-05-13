<?php

namespace App\Filament\Resources\FundraisingCampaigns\Pages;

use App\Filament\Resources\FundraisingCampaigns\FundraisingCampaignResource;
use App\Services\FundraisingCampaignService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFundraisingCampaign extends EditRecord
{
    protected static string $resource = FundraisingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $service = app(FundraisingCampaignService::class);
        $service->processAmountUpdate($this->record, $data);

        // Remove both fields from the data array since they're not real columns
        unset($data['additional_amount']);
        unset($data['manual_total_raised']);

        return $data;
    }
}
