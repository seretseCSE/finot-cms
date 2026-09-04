<?php

/** Shared date validation messages (birth dates, cross-field date order). */
return [
    'birth_future' => 'Guyyaan dhalootaa gara fuulduraa ta\'uu hin danda\'u.',
    'birth_too_young' => 'Umuriin yoo xiqqaate waggaa :years ta\'uu qaba.',
    'birth_too_old' => 'Guyyaan dhalootaa waggaa :years ol taasisa — waggaa mirkaneessi.',
    'retirement_before_birth' => 'Guyyaan soorama bahuu guyyaa dhalootaa booda ta\'uu qaba.',
    'ended_before_hired' => 'Guyyaan xumuraa guyyaa qacarrii wajjin wal-qixa yookaan booda ta\'uu qaba.',
    'promoted_before_hired' => 'Guyyaan guddinaa guyyaa qacarrii wajjin wal-qixa yookaan booda ta\'uu qaba.',
    'day_past' => 'Guyyaan kun darbeera — har\'a yookaan guyyaa fuulduraa filadhu.',
    'day_future' => 'Guyyaan kun gara fuulduraa ta\'uu hin danda\'u — har\'a yookaan guyyaa darbe filadhu.',
    'leave_start_past' => 'Gaaffiin hayyama boqonnaa har\'a yookaan booda jalqabuu qaba.',
    'due_past' => 'Guyyaan kaffaltii darbeera — har\'a yookaan guyyaa fuulduraa filadhu.',

    // Calendar vocabulary — consumed by App\Support\DateFormatter for SMS,
    // email and PDF rendering. Keep the three locales in lockstep.
    'eth_months' => [
        1 => 'Fulbaana', 2 => 'Onkoloolessa', 3 => 'Sadaasa', 4 => 'Muddee',
        5 => 'Amajjii', 6 => 'Guraandhala', 7 => 'Bitootessa', 8 => 'Elba',
        9 => 'Caamsaa', 10 => 'Waxabajjii', 11 => 'Adooleessa', 12 => 'Hagayya', 13 => 'Qaammee',
    ],
    'greg_months' => [
        1 => 'Amajjii', 2 => 'Guraandhala', 3 => 'Bitootessa', 4 => 'Elba',
        5 => 'Caamsaa', 6 => 'Waxabajjii', 7 => 'Adooleessa', 8 => 'Hagayya',
        9 => 'Fulbaana', 10 => 'Onkoloolessa', 11 => 'Sadaasa', 12 => 'Muddee',
    ],
    'weekdays' => [
        0 => 'Dilbata', 1 => 'Wiixata', 2 => 'Kibxata', 3 => 'Roobii',
        4 => 'Kamiisa', 5 => 'Jimaata', 6 => 'Sanbata',
    ],
    'era_ethiopian' => 'A.L.I.',
    'era_gregorian' => 'A.L.A.',
    'period_morning' => 'ganama',
    'period_afternoon' => 'waaree booda',
    'period_evening' => 'galgala',
    'period_night' => 'halkan',
];
