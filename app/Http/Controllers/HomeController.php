<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FundraisingCampaign;
use App\Models\MediaItem;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\ParentModel;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        try {
            $upcomingEvents = Event::where('date_time', '>=', now())
                ->where('status', 'Published')
                ->orderBy('date_time', 'asc')
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            $upcomingEvents = collect();
        }

        try {
            $recentPhotos = MediaItem::where('visibility', 'Public')
                ->where('type', 'Photo')
                ->latest()
                ->take(12)
                ->get();
        } catch (\Exception $e) {
            $recentPhotos = collect();
        }

        try {
            $campaigns = FundraisingCampaign::where('status', 'Active')
                ->latest()
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            $campaigns = collect();
        }

        try {
            $stats = Cache::remember('homepage.member_stats', now()->addMinutes(10), function () {
                $memberCounts = Member::withoutDepartmentScope()
                    ->selectRaw('member_type, COUNT(*) as count')
                    ->groupBy('member_type')
                    ->pluck('count', 'member_type');

                return [
                    'total' => Member::withoutDepartmentScope()->count(),
                    'kids' => $memberCounts->get('Kids', 0),
                    'youth' => $memberCounts->get('Youth', 0),
                    'adults' => $memberCounts->get('Adult', 0),
                    'groups' => MemberGroup::count(),
                    'parents' => ParentModel::count(),
                ];
            });
        } catch (\Exception $e) {
            $stats = [
                'total' => 0,
                'kids' => 0,
                'youth' => 0,
                'adults' => 0,
                'groups' => 0,
                'parents' => 0,
            ];
        }

        return view('public.home', compact(
            'upcomingEvents',
            'recentPhotos',
            'campaigns',
            'stats',
        ));
    }
}
