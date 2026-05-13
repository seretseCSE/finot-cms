<?php

namespace App\Services;

use App\Models\FundraisingCampaign;

class FundraisingCampaignService
{
    /**
     * Process amount update for fundraising campaign.
     *
     * @param FundraisingCampaign $campaign The campaign
     * @param array $data The form data
     * @return void
     */
    public function processAmountUpdate(FundraisingCampaign $campaign, array $data): void
    {
        // Priority: Manual override takes precedence over additional amount
        if (isset($data['manual_total_raised']) && $data['manual_total_raised'] !== null && $data['manual_total_raised'] >= 0) {
            $this->applyManualOverride($campaign, (float) $data['manual_total_raised']);
        } elseif (isset($data['additional_amount']) && $data['additional_amount'] > 0) {
            $this->applyAdditionalAmount($campaign, (float) $data['additional_amount']);
        }
    }

    /**
     * Apply manual override to campaign total raised.
     *
     * @param FundraisingCampaign $campaign The campaign
     * @param float $amount The manual total raised amount
     * @return void
     */
    public function applyManualOverride(FundraisingCampaign $campaign, float $amount): void
    {
        $campaign->manual_total_raised = $amount;
        $campaign->save();
    }

    /**
     * Apply additional amount to campaign total raised.
     *
     * @param FundraisingCampaign $campaign The campaign
     * @param float $amount The additional amount
     * @return void
     */
    public function applyAdditionalAmount(FundraisingCampaign $campaign, float $amount): void
    {
        $campaign->additional_amount = $amount;
        $campaign->save();
    }
}
