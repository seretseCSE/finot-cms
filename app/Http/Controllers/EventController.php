<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventController extends Controller
{
    public function show(Event $event)
    {
        if (! in_array($event->status, ['Published', 'Ongoing', 'Full'])) {
            abort(404);
        }

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
