<?php

namespace App\Filament\Resources\ContributionAmountResource\Pages;

use App\Filament\Resources\ContributionAmountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContributionAmount extends CreateRecord
{
    protected static string $resource = ContributionAmountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getHeading(): string
    {
        return 'Create Contribution Amount';
    }

    public function getSubheading(): string
    {
        return 'Define contribution amounts for member groups by month';
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $groupIds = is_array($data['group_id']) ? $data['group_id'] : [$data['group_id']];
        $monthNames = is_array($data['month_name']) ? $data['month_name'] : [$data['month_name']];

        $firstRecord = null;

        foreach ($groupIds as $groupId) {
            foreach ($monthNames as $monthName) {
                $recordData = $data;
                $recordData['group_id'] = $groupId;
                $recordData['month_name'] = $monthName;

                // Add created_by if user is authenticated
                $recordData['created_by'] = auth()->id();

                $record = static::getModel()::create($recordData);
                if (! $firstRecord) {
                    $firstRecord = $record;
                }
            }
        }

        return $firstRecord ?? new \App\Models\ContributionAmount();
    }
}
