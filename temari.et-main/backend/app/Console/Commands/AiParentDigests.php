<?php

namespace App\Console\Commands;

use App\Models\AiSubscription;
use App\Models\AttendanceRecord;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Notify\Notifier;
use Illuminate\Console\Command;

/**
 * Weekly per-child digest for PREMIUM (AI-subscribed) parents — in-app
 * only, deduped per ISO week. Deliberately template-based (no model call):
 * a digest for thousands of children must cost nothing; the AI depth lives
 * in /ai where the parent can ask follow-ups.
 */
class AiParentDigests extends Command
{
    protected $signature = 'ai:parent-digests';

    protected $description = 'Send the weekly child digest to AI-subscribed parents';

    public function handle(Notifier $notifier): int
    {
        $week = now()->format('o-W');
        $weekStart = now()->startOfWeek()->toDateString();
        $sent = 0;

        User::query()
            ->whereIn('id', AiSubscription::query()
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->select('user_id'))
            ->whereHas('parentProfile')
            ->with('parentProfile')
            ->chunkById(50, function ($parents) use ($notifier, $week, $weekStart, &$sent): void {
                foreach ($parents as $parent) {
                    $links = StudentGuardian::query()
                        ->where('parent_id', $parent->parentProfile->id)
                        ->where('is_active', true)
                        ->where('can_view_attendance', true)
                        ->with('student:id,first_name,father_name')
                        ->get();

                    foreach ($links as $link) {
                        $student = $link->student;

                        if ($student === null) {
                            continue;
                        }

                        $counts = AttendanceRecord::query()
                            ->where('student_id', $student->id)
                            ->where('date', '>=', $weekStart)
                            ->selectRaw('status, count(*) as c')
                            ->groupBy('status')
                            ->pluck('c', 'status');

                        if ($counts->sum() === 0) {
                            continue;
                        }

                        $summary = sprintf(
                            'Present %d, late %d, absent %d this week. Ask Temari AI for the full picture.',
                            (int) ($counts['present'] ?? 0),
                            (int) ($counts['late'] ?? 0),
                            (int) ($counts['absent'] ?? 0),
                        );

                        $notifier->toUser($parent, 'ai.parent_digest', [
                            'student' => $student->first_name,
                            'summary' => $summary,
                        ], [
                            'link' => '/ai',
                            'dedupeKey' => "ai_digest:{$student->id}:{$parent->id}:{$week}",
                        ]);

                        $sent++;
                    }
                }
            });

        $this->info("Digests sent: {$sent}.");

        return self::SUCCESS;
    }
}
