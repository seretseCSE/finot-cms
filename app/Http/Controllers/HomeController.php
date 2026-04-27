<?php

namespace App\Http\Controllers;

use App\Models\LibraryResource;
use App\Models\Event;
use App\Models\BlogPost;
use App\Models\FAQ;

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

        return view('public.home', compact('featuredLibraryResources', 'totalLibraryResources', 'upcomingEvents', 'recentPosts', 'departments', 'faqs'));
    }
}
