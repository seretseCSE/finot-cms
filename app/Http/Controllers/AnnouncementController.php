<?php

namespace App\Http\Controllers;

use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        return redirect()->route('news', [], 301);
    }

    public function show($id)
    {
        $announcement = Announcement::where('status', 'Active')->findOrFail($id);

        return view('public.announcements.show', compact('announcement'));
    }
}
