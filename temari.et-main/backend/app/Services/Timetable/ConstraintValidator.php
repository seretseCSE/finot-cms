<?php

namespace App\Services\Timetable;

use App\Models\SubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;

/**
 * Hard-constraint checks for one manual slot placement, shared by the slot
 * endpoints (instant feedback while dragging cells around) and the publish
 * gate. Codes are stable identifiers the frontend translates:
 *
 *   invalid_cell · section_clash · teacher_clash · room_clash ·
 *   teacher_unavailable · teacher_daily_max
 *
 * Soft concerns (same subject twice a day, heavy subject late) come back as
 * WARNINGS — never blocking, always visible.
 */
class ConstraintValidator
{
    public const TEACHER_DAILY_MAX = 6;

    public function __construct(private readonly TimetableContext $context)
    {
    }

    public static function for(TimetableVersion $version): self
    {
        return new self(new TimetableContext($version));
    }

    /**
     * @return array{violations: list<array<string, mixed>>, warnings: list<array<string, mixed>>}
     */
    public function checkPlacement(
        SubjectAssignment $assignment,
        int $day,
        int $period,
        ?int $roomId = null,
        ?int $ignoreSlotId = null,
    ): array {
        $ctx = $this->context;
        $violations = [];
        $warnings = [];

        if (! $ctx->dayExists($day) || ! $ctx->periodExists($period)) {
            return [
                'violations' => [['code' => 'invalid_cell', 'day' => $day, 'period' => $period]],
                'warnings' => [],
            ];
        }

        $cellSlots = $ctx->slots->filter(
            fn (TimetableSlot $s) => $s->day_of_week === $day
                && $s->period_number === $period
                && $s->id !== $ignoreSlotId,
        );

        foreach ($cellSlots as $slot) {
            $other = $ctx->assignments->get($slot->subject_assignment_id) ?? $slot->subjectAssignment;

            if ($other === null) {
                continue;
            }

            if ($other->section_id === $assignment->section_id) {
                $violations[] = [
                    'code' => 'section_clash',
                    'section' => TimetableContext::sectionLabel($assignment->section),
                    'subject' => $other->subject?->name,
                ];
            }

            if ($assignment->employee_id !== null && $other->employee_id === $assignment->employee_id) {
                $violations[] = [
                    'code' => 'teacher_clash',
                    'section' => TimetableContext::sectionLabel($other->section),
                    'subject' => $other->subject?->name,
                ];
            }

            if ($roomId !== null && $slot->room_id === $roomId) {
                $violations[] = [
                    'code' => 'room_clash',
                    'subject' => $other->subject?->name,
                    'section' => TimetableContext::sectionLabel($other->section),
                ];
            }
        }

        if ($ctx->teacherBlocked($assignment->employee_id, $day, $period)) {
            $violations[] = ['code' => 'teacher_unavailable', 'day' => $day, 'period' => $period];
        }

        if ($assignment->employee_id !== null) {
            $dayLoad = $ctx->slots->filter(function (TimetableSlot $s) use ($ctx, $assignment, $day, $ignoreSlotId): bool {
                if ($s->day_of_week !== $day || $s->id === $ignoreSlotId) {
                    return false;
                }
                $other = $ctx->assignments->get($s->subject_assignment_id);

                return $other !== null && $other->employee_id === $assignment->employee_id;
            })->count();

            if ($dayLoad + 1 > self::TEACHER_DAILY_MAX) {
                $violations[] = ['code' => 'teacher_daily_max', 'max' => self::TEACHER_DAILY_MAX];
            }
        }

        // ——— warnings (soft) ———
        $sameSubjectToday = $ctx->slots->filter(function (TimetableSlot $s) use ($ctx, $assignment, $day, $ignoreSlotId): bool {
            if ($s->day_of_week !== $day || $s->id === $ignoreSlotId) {
                return false;
            }
            $other = $ctx->assignments->get($s->subject_assignment_id);

            return $other !== null
                && $other->section_id === $assignment->section_id
                && $other->subject_id === $assignment->subject_id;
        })->count();

        if ($sameSubjectToday >= max(1, $assignment->block_size)) {
            $warnings[] = ['code' => 'subject_repeats_day', 'count' => $sameSubjectToday + 1];
        }

        $weight = (int) ($assignment->subject->weight ?? 3);
        $lastPeriod = $ctx->periods !== [] ? max($ctx->periods) : null;

        if ($weight >= 4 && $day === 5 && $period === $lastPeriod) {
            $warnings[] = ['code' => 'heavy_friday_last'];
        }

        return ['violations' => $violations, 'warnings' => $warnings];
    }

    /**
     * Whole-version audit (publish gate): every slot re-checked against the
     * others. Returns hard violations only, grouped per slot.
     *
     * @return list<array<string, mixed>>
     */
    public function auditVersion(): array
    {
        $problems = [];

        foreach ($this->context->slots as $slot) {
            $assignment = $this->context->assignments->get($slot->subject_assignment_id);

            if ($assignment === null) {
                continue;
            }

            $result = $this->checkPlacement(
                $assignment,
                $slot->day_of_week,
                $slot->period_number,
                $slot->room_id,
                $slot->id,
            );

            foreach ($result['violations'] as $violation) {
                $problems[] = $violation + [
                    'slot_id' => $slot->id,
                    'day' => $slot->day_of_week,
                    'period' => $slot->period_number,
                ];
            }
        }

        return $problems;
    }
}
