<?php

namespace App\Http\Controllers;

use App\Models\FundraisingCampaign;

class FundraisingController extends Controller
{
    /**
     * Display fundraising progress page
     */
    public function index()
    {
        $campaigns = FundraisingCampaign::visible()
            ->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalRaised = $campaigns->sum('total_raised');
        $totalTarget = $campaigns->sum('target_amount');
        $overallProgress = $totalTarget > 0 ? ($totalRaised / $totalTarget) * 100 : 0;

        return view('public.fundraising', compact(
            'campaigns',
            'totalRaised',
            'totalTarget',
            'overallProgress'
        ));
    }

    /**
     * API endpoint for fundraising data (for AJAX calls)
     */
    public function api()
    {
        $campaigns = FundraisingCampaign::visible()
            ->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($campaign) {
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

        $totalRaised = $campaigns->sum('total_raised');
        $totalTarget = $campaigns->sum('target_amount');
        $overallProgress = $totalTarget > 0 ? ($totalRaised / $totalTarget) * 100 : 0;

        return response()->json([
            'campaigns' => $campaigns,
            'summary' => [
                'total_raised' => $totalRaised,
                'total_target' => $totalTarget,
                'overall_progress' => round($overallProgress, 1),
                'active_campaigns' => $campaigns->where('status', 'Active')->count(),
                'completed_campaigns' => $campaigns->where('status', 'Completed')->count(),
            ],
        ]);
    }
}
