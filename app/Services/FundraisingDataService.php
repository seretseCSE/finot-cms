<?php

namespace App\Services;

use App\Models\FundraisingCampaign;

class FundraisingDataService
{
    /**
     * Get fundraising data for public view
     */
    public function getPublicData(): array
    {
        $campaigns = $this->getVisibleCampaigns();
        $summary = $this->calculateSummary($campaigns);

        return [
            'campaigns' => $campaigns,
            'totalRaised' => $summary['totalRaised'],
            'totalTarget' => $summary['totalTarget'],
            'overallProgress' => $summary['overallProgress'],
        ];
    }

    /**
     * Get fundraising data for API response
     */
    public function getApiData(): array
    {
        $campaigns = $this->getVisibleCampaigns();
        $apiCampaigns = $this->transformForApi($campaigns);
        $summary = $this->calculateSummary($campaigns);

        return [
            'campaigns' => $apiCampaigns,
            'summary' => [
                'total_raised' => $summary['totalRaised'],
                'total_target' => $summary['totalTarget'],
                'overall_progress' => round($summary['overallProgress'], 1),
                'active_campaigns' => $campaigns->where('status', 'Active')->count(),
                'completed_campaigns' => $campaigns->where('status', 'Completed')->count(),
            ],
        ];
    }

    /**
     * Get visible campaigns
     */
    private function getVisibleCampaigns()
    {
        return FundraisingCampaign::visible()
            ->with('donations')
            ->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Transform campaigns for API response
     */
    private function transformForApi($campaigns)
    {
        return $campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->id,
                'campaign_name' => $campaign->campaign_name,
                'target_amount' => $campaign->target_amount,
                'total_raised' => $campaign->total_raised,
                'progress_percentage' => round($campaign->progress_percentage, 1),
                'status' => $campaign->status,
                'campaign_category' => $campaign->campaign_category,
                'description' => $campaign->description,
                'start_date' => $campaign->start_date->format('Y-m-d'),
                'end_date' => $campaign->end_date?->format('Y-m-d'),
                'days_remaining' => $campaign->days_remaining,
                'bank_account_info' => $campaign->bank_account_info,
                'featured_image' => $campaign->featured_image,
            ];
        });
    }

    /**
     * Calculate summary statistics
     */
    private function calculateSummary($campaigns): array
    {
        $totalRaised = $campaigns->sum('total_raised');
        $totalTarget = $campaigns->sum('target_amount');
        $overallProgress = $totalTarget > 0 ? ($totalRaised / $totalTarget) * 100 : 0;

        return [
            'totalRaised' => $totalRaised,
            'totalTarget' => $totalTarget,
            'overallProgress' => $overallProgress,
        ];
    }
}
