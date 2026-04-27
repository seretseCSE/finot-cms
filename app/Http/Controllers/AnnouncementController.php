<?php

namespace App\Http\Controllers;

use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::where('status', 'Active')
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->latest('published_at')
            ->paginate(12);

        return view('public.announcements.index', compact('announcements'));
    }

    public function show($id)
    {
        $announcement = Announcement::where('status', 'Active')->findOrFail($id);

        return view('public.announcements.show', compact('announcement'));
    }
}
