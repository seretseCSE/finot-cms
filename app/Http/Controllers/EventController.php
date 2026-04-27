<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $currentMonth = $request->input('month', now()->month);
        $currentYear = $request->input('year', now()->year);

        $startOfMonth = Carbon::create($currentYear, $currentMonth, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

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

        return view('public.events', compact(
            'events',
            'upcomingEvents',
            'calendarEvents',
            'currentMonth',
            'currentYear',
            'startOfMonth'
        ));
    }

    public function show(Event $event)
    {
        // Only show published, ongoing, or full events
        if (!in_array($event->status, ['Published', 'Ongoing', 'Full'])) {
            abort(404);
        }

        // Get related events (same month)
        $relatedEvents = Event::whereIn('status', ['Published', 'Ongoing', 'Full'])
            ->where('id', '!=', $event->id)
            ->whereMonth('date_time', $event->date_time->month)
            ->whereYear('date_time', $event->date_time->year)
            ->orderBy('date_time', 'asc')
            ->take(3)
            ->get();

        return view('public.event-details', compact('event', 'relatedEvents'));
    }
}
