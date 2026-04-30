<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $currentMonth = $request->input('month', now()->month);
        $currentYear = $request->input('year', now()->year);

        $startOfMonth = Carbon::create($currentYear, $currentMonth, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $announcements = Announcement::where('status', 'Active')
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->latest('published_at')
            ->paginate(12, ['*'], 'announcements_page');

        $events = Event::whereIn('status', ['Published', 'Ongoing', 'Full'])
            ->whereBetween('date_time', [$startOfMonth->copy()->startOfMonth(), $endOfMonth->copy()->endOfMonth()])
            ->orderBy('date_time', 'asc')
            ->get();

        $upcomingEvents = Event::whereIn('status', ['Published', 'Ongoing', 'Full'])
            ->where('date_time', '>=', now()->startOfDay())
            ->orderBy('date_time', 'asc')
            ->take(10)
            ->get();

        $calendarEvents = $events->groupBy(function ($event) {
            return $event->date_time->format('Y-m-d');
        });

        $currentPage = 'news';
        $activeTab = $request->query('tab', 'announcements');

        return view('public.news', compact(
            'announcements',
            'events',
            'upcomingEvents',
            'calendarEvents',
            'currentMonth',
            'currentYear',
            'startOfMonth',
            'currentPage',
            'activeTab'
        ));
    }
}
