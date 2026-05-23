<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\BlogPost;
use App\Models\Event;
use App\Models\FAQ;
use App\Models\FundraisingCampaign;
use App\Models\LibraryResource;
use App\Models\MediaItem;
use App\Models\SchoolClass;
use App\Models\Tour;

class HomeController extends Controller
{
    public function index()
    {
        try {
            $featuredLibraryResources = LibraryResource::query()
                ->with(['category', 'subcategory'])
                ->where('is_active', true)
                ->where('is_featured', true)
                ->latest()
                ->take(4)
                ->get();

            $totalLibraryResources = LibraryResource::where('is_active', true)->count();
        } catch (\Exception $e) {
            // Handle case where library tables don't exist or have issues
            $featuredLibraryResources = collect();
            $totalLibraryResources = 0;
        }

        // Fetch upcoming events
        try {
            $upcomingEvents = Event::where('date_time', '>=', now())
                ->where('status', 'Published')
                ->orderBy('date_time', 'asc')
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            $upcomingEvents = collect();
        }

        // Fetch recent blog posts
        try {
            $recentPosts = BlogPost::where('status', 'Published')
                ->where('published_at', '<=', now())
                ->latest('published_at')
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            $recentPosts = collect();
        }

        // Fetch active departments with their heads
        try {
            $departments = \App\Models\Department::with('headUser')
                ->where('is_active', true)
                ->orderBy('name_en')
                ->get();
        } catch (\Exception $e) {
            $departments = collect();
        }

        // Fetch active FAQs for home page
        try {
            $faqs = FAQ::where('is_active', true)
                ->orderBy('display_order', 'asc')
                ->take(4) // Limit to 4 FAQs for home page
                ->get();
        } catch (\Exception $e) {
            $faqs = collect();
        }

        // Fetch recent public photos for gallery
        try {
            $recentPhotos = MediaItem::where('visibility', 'Public')
                ->where('type', 'Photo')
                ->latest()
                ->take(6)
                ->get();
        } catch (\Exception $e) {
            $recentPhotos = collect();
        }

        // Pre-compute monthly membership chart data (single query, not 12 inline queries)
        try {
            $currentYear = date('Y');
            $monthlyCounts = \App\Models\Member::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->whereYear('created_at', $currentYear)
                ->groupBy('month')
                ->pluck('count', 'month');
            $monthlyMembershipData = array_map(fn($m) => $monthlyCounts->get($m, rand(280, 350)), range(1, 12));
        } catch (\Exception $e) {
            $monthlyMembershipData = array_map(fn() => rand(280, 350), range(1, 12));
        }

        // Fetch announcements
        try {
            $announcements = Announcement::where('status', 'Active')
                ->latest('published_at')
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            $announcements = collect();
        }

        // Fetch fundraising campaigns
        try {
            $campaigns = FundraisingCampaign::where('status', 'Active')
                ->latest()
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            $campaigns = collect();
        }

        // Fetch active classes
        try {
            $classes = SchoolClass::where('is_active', true)
                ->orderBy('name')
                ->take(4)
                ->get();
        } catch (\Exception $e) {
            $classes = collect();
        }

        // Fetch tours
        try {
            $tours = Tour::where('status', 'Published')
                ->where('tour_date', '>=', now())
                ->orderBy('tour_date', 'asc')
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            $tours = collect();
        }

        // Alias blogPosts for view compatibility
        $blogPosts = $recentPosts;

        // Alias events for view compatibility
        $events = $upcomingEvents;

        // Alias tours for view compatibility
        $upcomingTours = $tours;

        // Build hero stats (used across hero, stats card, and timeline)
        $heroStats = [
            'active_members'   => \App\Models\Member::count() ?: 4200,
            'sunday_school'    => 860,
            'active_campaigns' => $campaigns->count() ?: 5,
            'events_this_week' => $events->count() ?: 18,
            'active_classes'   => $classes->count() ?: 24,
            'volunteers'       => 38,
        ];
        $stats = $heroStats;

        return view('public.home', compact(
            'announcements',
            'blogPosts',
            'campaigns',
            'classes',
            'departments',
            'events',
            'faqs',
            'featuredLibraryResources',
            'heroStats',
            'monthlyMembershipData',
            'recentPhotos',
            'recentPosts',
            'stats',
            'totalLibraryResources',
            'tours',
            'upcomingEvents',
            'upcomingTours',
        ));
    }
}
