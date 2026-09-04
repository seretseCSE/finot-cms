<?php

/** Shared date validation messages (birth dates, cross-field date order). */
return [
    'birth_future' => 'The birth date cannot be in the future.',
    'birth_too_young' => 'The person must be at least :years year(s) old.',
    'birth_too_old' => 'The birth date makes this person older than :years years — check the year.',
    'retirement_before_birth' => 'The retirement date must be after the birth date.',
    'ended_before_hired' => 'The end date must be on or after the hire date.',
    'promoted_before_hired' => 'The promotion date must be on or after the hire date.',
    'day_past' => 'This date has already passed — pick today or a later day.',
    'day_future' => 'This date cannot be in the future — pick today or an earlier day.',
    'leave_start_past' => 'A leave request must start today or later.',
    'due_past' => 'The due date has already passed — pick today or a later day.',

    // Calendar vocabulary — consumed by App\Support\DateFormatter for SMS,
    // email and PDF rendering. Keep the three locales in lockstep.
    'eth_months' => [
        1 => 'Meskerem', 2 => 'Tikimt', 3 => 'Hidar', 4 => 'Tahsas',
        5 => 'Tir', 6 => 'Yekatit', 7 => 'Megabit', 8 => 'Miazia',
        9 => 'Ginbot', 10 => 'Sene', 11 => 'Hamle', 12 => 'Nehase', 13 => 'Pagume',
    ],
    'greg_months' => [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ],
    // 0 = Sunday … 6 = Saturday (Carbon dayOfWeek).
    'weekdays' => [
        0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
    ],
    'era_ethiopian' => 'E.C.',
    'era_gregorian' => 'G.C.',
    // Ethiopian-clock day periods (the day counted from dawn).
    'period_morning' => 'morning',
    'period_afternoon' => 'afternoon',
    'period_evening' => 'evening',
    'period_night' => 'night',
];
