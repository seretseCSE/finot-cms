<?php

namespace App\Services\Chat;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\School;
use App\Models\Section;
use Illuminate\Support\Facades\Cache;

/**
 * Auto-provisions the SYSTEM channels every school gets without setup —
 * idempotent on conversations.system_key, called lazily from the chat index
 * (cheap cache flag) so new branches/sections self-heal without observers:
 *
 *  - school announcements  (whole community, admin-posted, reactions only)
 *  - per branch: staff room (all staff, open) + branch announcements
 *    (staff + families, admin-posted)
 *  - per active section: the classroom channel (section staff + parents +
 *    students-when-enabled). No year scoping needed: the audience derives
 *    from LIVE enrollments, so it rolls over with the students.
 *
 * Titles: system channels store a `settings.system` kind the frontend
 * renders localized; the stored title is the fallback.
 */
class ChannelProvisioner
{
    private const CACHE_MINUTES = 30;

    public function ensureForSchool(School $school): void
    {
        Cache::remember("chat:provisioned:school:{$school->id}", now()->addMinutes(self::CACHE_MINUTES), function () use ($school): bool {
            $schoolWide = Conversation::query()->firstOrCreate(
                ['system_key' => "school:{$school->id}:announcements"],
                [
                    'school_id' => $school->id,
                    'branch_id' => null,
                    'kind' => 'channel',
                    'title' => 'School announcements',
                    'settings' => ['posting' => 'admins', 'system' => 'school_announcements'],
                ],
            );
            foreach (['staff', 'parents', 'students'] as $audience) {
                $schoolWide->targets()->firstOrCreate(['audience' => $audience], []);
            }

            foreach (Branch::query()->where('school_id', $school->id)->where('is_active', true)->get() as $branch) {
                $this->provisionBranch($branch);
            }

            return true;
        });
    }

    private function provisionBranch(Branch $branch): void
    {
        $staffRoom = Conversation::query()->firstOrCreate(
            ['system_key' => "branch:{$branch->id}:staff_room"],
            [
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'kind' => 'channel',
                'title' => "{$branch->name} · Staff room",
                'settings' => ['posting' => 'all', 'system' => 'staff_room'],
            ],
        );
        $staffRoom->targets()->firstOrCreate(['audience' => 'staff', 'branch_id' => $branch->id], []);

        $announcements = Conversation::query()->firstOrCreate(
            ['system_key' => "branch:{$branch->id}:announcements"],
            [
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'kind' => 'channel',
                'title' => "{$branch->name} · Announcements",
                'settings' => ['posting' => 'admins', 'system' => 'branch_announcements'],
            ],
        );
        foreach (['staff', 'parents', 'students'] as $audience) {
            $announcements->targets()->firstOrCreate(['audience' => $audience, 'branch_id' => $branch->id], []);
        }

        Section::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->with('gradeLevel:id,name')
            ->get()
            ->each(function (Section $section) use ($branch): void {
                $classroom = Conversation::query()->firstOrCreate(
                    ['system_key' => "classroom:{$section->id}"],
                    [
                        'school_id' => $branch->school_id,
                        'branch_id' => $branch->id,
                        'kind' => 'channel',
                        'section_id' => $section->id,
                        'title' => trim(($section->gradeLevel?->name ?? '')." {$section->name}"),
                        'settings' => ['posting' => 'all', 'system' => 'classroom'],
                    ],
                );

                foreach (['staff', 'parents', 'students'] as $audience) {
                    $classroom->targets()->firstOrCreate(
                        ['audience' => $audience, 'branch_id' => $branch->id, 'section_id' => $section->id],
                        [],
                    );
                }
            });
    }
}
