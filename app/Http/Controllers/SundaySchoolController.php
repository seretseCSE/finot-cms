<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Member;

class SundaySchoolController extends Controller
{
    public function index()
    {
        $childrenCount = Member::withoutDepartmentScope()->where('member_type', 'Kids')->count();
        $youthCount = Member::withoutDepartmentScope()->where('member_type', 'Youth')->count();
        $adultCount = Member::withoutDepartmentScope()->where('member_type', 'Adult')->count();
        $totalMembers = Member::withoutDepartmentScope()->count();
        $yearsServing = (int) (now()->year - 1992) + 8;

        $programs = [
            [
                'icon' => '🌱',
                'name' => "Children's Program",
                'name_am' => 'ህጻናት',
                'description' => 'Building faith foundations for grades 1-8 through engaging lessons, activities, and fellowship.',
                'count' => $childrenCount,
                'grades' => 'Grades 1-8',
            ],
            [
                'icon' => '🔥',
                'name' => 'Youth Program',
                'name_am' => 'አዳጊ',
                'description' => 'Deepening spiritual discipline for grades 9-12 with mentorship and peer community.',
                'count' => $youthCount,
                'grades' => 'Grades 9-12',
            ],
            [
                'icon' => '⭐',
                'name' => 'Young Adults',
                'name_am' => 'ወጣት',
                'description' => 'Equipping adults 18+ for leadership, service, and lifelong faith formation.',
                'count' => $adultCount,
                'grades' => '18+',
            ],
            [
                'icon' => '🌍',
                'name' => 'Distance Learning',
                'name_am' => 'የርቀት',
                'description' => 'Online spiritual education for those unable to attend in person, anywhere in the world.',
                'count' => max(0, $totalMembers - $childrenCount - $youthCount - $adultCount),
                'grades' => 'Online',
            ],
        ];

        $events = Event::where('status', 'Published')
            ->orWhere('status', 'Ongoing')
            ->latest('date_time')
            ->take(6)
            ->get();

        $announcements = Announcement::whereIn('status', ['Active', 'Scheduled'])
            ->latest('start_date')
            ->take(3)
            ->get();

        $stats = [
            ['label' => 'Children Enrolled', 'count' => $childrenCount + $youthCount + $adultCount, 'suffix' => '+'],
            ['label' => 'Classes & Groups', 'count' => 12, 'suffix' => ''],
            ['label' => 'Years Serving', 'count' => $yearsServing, 'suffix' => '+'],
            ['label' => 'Total Members', 'count' => $totalMembers, 'suffix' => '+'],
        ];

        return view('public.sunday-school', compact('programs', 'events', 'announcements', 'stats'));
    }
}
