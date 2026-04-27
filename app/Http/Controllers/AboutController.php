<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Member;

class AboutController extends Controller
{
    public function index()
    {
        $page = Page::where('slug', 'about')
            ->where('status', 'Published')
            ->first();

        // Get member statistics for stats section
        $stats = [
            'kids' => Member::where('member_type', 'Kids')->where('status', 'Active')->count(),
            'youth' => Member::where('member_type', 'Youth')->where('status', 'Active')->count(),
            'adults' => Member::where('member_type', 'Adult')->where('status', 'Active')->count(),
            'total' => Member::where('status', 'Active')->count(),
        ];

        return view('public.about', compact('page', 'stats'));
    }
}
