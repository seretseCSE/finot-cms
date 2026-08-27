<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One time block of the term's period schedule. Class rows carry the
 * `period_number` timetable slots reference; break/lunch/flag rows shape the
 * day (and forbid double periods from straddling them).
 */
#[Fillable(['term_id', 'sequence', 'type', 'period_number', 'label', 'starts_at', 'ends_at'])]
class TermPeriod extends Model
{
    public const TYPES = ['class', 'break', 'lunch', 'flag'];

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Default Ethiopian school day starting at 08:30: 3 classes, a 15-minute
     * break, 2 classes, a 45-minute lunch, then 2 closing classes — every
     * class `period_minutes` (45 by default) long, starting at the term's
     * class window. Returns attribute arrays (unsaved) so callers can persist
     * or preview.
     *
     * @return list<array{sequence: int, type: string, period_number: ?int, label: ?string, starts_at: string, ends_at: string}>
     */
    public static function defaultsFor(Term $term): array
    {
        $minutes = max(20, (int) ($term->period_minutes ?? 45));
        $cursor = strtotime('1970-01-01 '.($term->class_starts_at ?? '08:30'));

        // type · length in minutes (class length comes from the term).
        $pattern = [
            ['class', $minutes], ['class', $minutes], ['class', $minutes],
            ['break', 15],
            ['class', $minutes], ['class', $minutes],
            ['lunch', 45],
            ['class', $minutes], ['class', $minutes],
        ];

        $rows = [];
        $sequence = 1;
        $period = 1;

        foreach ($pattern as [$type, $length]) {
            $rows[] = [
                'sequence' => $sequence++,
                'type' => $type,
                'period_number' => $type === 'class' ? $period++ : null,
                'label' => match ($type) {
                    'break' => 'Break',
                    'lunch' => 'Lunch',
                    default => null,
                },
                'starts_at' => date('H:i', $cursor),
                'ends_at' => date('H:i', $cursor + $length * 60),
            ];
            $cursor += $length * 60;
        }

        return $rows;
    }
}
