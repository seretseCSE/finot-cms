<?php

namespace App\Filament\Resources\FundraisingCampaigns\Pages;

use App\Filament\Resources\FundraisingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFundraisingCampaign extends EditRecord
{
    protected static string $resource = FundraisingResource::class;

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
        // Priority: Manual override takes precedence over additional amount
        if (isset($data['manual_total_raised']) && $data['manual_total_raised'] !== null && $data['manual_total_raised'] >= 0) {
            // Use manual override for error correction - ensure it's a number
            $this->record->manual_total_raised = (float) $data['manual_total_raised'];
        } elseif (isset($data['additional_amount']) && $data['additional_amount'] > 0) {
            // Use additional amount for normal updates
            $this->record->additional_amount = (float) $data['additional_amount'];
        }

        // Remove both fields from the data array since they're not real columns
        unset($data['additional_amount']);
        unset($data['manual_total_raised']);

        return $data;
    }
}
