<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Event;
use App\Models\FAQ;
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
            $upcomingEvents = Cache::remember('homepage.upcoming_events', now()->addMinutes(10), fn () =>
                Event::where('date_time', '>=', now())
                    ->where('status', 'Published')
                    ->orderBy('date_time', 'asc')
                    ->take(3)
                    ->get()
            );
        } catch (\Exception $e) {
            $upcomingEvents = collect();
        }

        try {
            $recentPhotos = Cache::remember('homepage.recent_photos', now()->addMinutes(30), fn () =>
                MediaItem::where('visibility', 'Public')
                    ->where('type', 'Photo')
                    ->latest()
                    ->take(12)
                    ->get()
            );
        } catch (\Exception $e) {
            $recentPhotos = collect();
        }

        try {
            $recentPosts = Cache::remember('homepage.recent_posts', now()->addMinutes(10), fn () =>
                BlogPost::where('status', 'Published')
                    ->where('published_at', '<=', now())
                    ->latest('published_at')
                    ->take(3)
                    ->get()
            );
        } catch (\Exception $e) {
            $recentPosts = collect();
        }

        try {
            $campaigns = Cache::remember('homepage.campaigns', now()->addMinutes(15), fn () =>
                FundraisingCampaign::where('status', 'Active')
                    ->latest()
                    ->take(3)
                    ->get()
            );
        } catch (\Exception $e) {
            $campaigns = collect();
        }

        try {
            $faqs = Cache::remember('homepage.faqs', now()->addMinutes(30), function () {
                return FAQ::where('is_active', true)
                    ->where('is_featured', true)
                    ->orderBy('display_order')
                    ->take(6)
                    ->get();
            });
        } catch (\Exception $e) {
            $faqs = collect();
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
                    'departments' => \App\Models\Department::count(),
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
                'departments' => 0,
            ];
        }

        return view('public.home', compact(
            'upcomingEvents',
            'recentPhotos',
            'recentPosts',
            'campaigns',
            'faqs',
            'stats',
        ));
    }
}
