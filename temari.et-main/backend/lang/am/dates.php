<?php

/** Shared date validation messages (birth dates, cross-field date order). */
return [
    'birth_future' => 'የትውልድ ቀን ወደፊት ሊሆን አይችልም።',
    'birth_too_young' => 'ዕድሜው ቢያንስ :years ዓመት መሆን አለበት።',
    'birth_too_old' => 'የትውልድ ቀኑ ከ:years ዓመት በላይ ያደርገዋል — ዓመቱን ያረጋግጡ።',
    'retirement_before_birth' => 'የጡረታ ቀን ከትውልድ ቀን በኋላ መሆን አለበት።',
    'ended_before_hired' => 'የመጨረሻ ቀን ከቅጥር ቀን ጋር እኩል ወይም በኋላ መሆን አለበት።',
    'promoted_before_hired' => 'የዕድገት ቀን ከቅጥር ቀን ጋር እኩል ወይም በኋላ መሆን አለበት።',
    'day_past' => 'ይህ ቀን አልፏል — ዛሬን ወይም ወደፊት ያለ ቀን ይምረጡ።',
    'day_future' => 'ይህ ቀን ወደፊት ሊሆን አይችልም — ዛሬን ወይም ያለፈ ቀን ይምረጡ።',
    'leave_start_past' => 'የፈቃድ ጥያቄ ከዛሬ ጀምሮ ወይም ወደፊት መጀመር አለበት።',
    'due_past' => 'የመክፈያ ቀኑ አልፏል — ዛሬን ወይም ወደፊት ያለ ቀን ይምረጡ።',

    // Calendar vocabulary — consumed by App\Support\DateFormatter for SMS,
    // email and PDF rendering. Keep the three locales in lockstep.
    'eth_months' => [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
        5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
        9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜ',
    ],
    'greg_months' => [
        1 => 'ጃንዋሪ', 2 => 'ፌብሯሪ', 3 => 'ማርች', 4 => 'ኤፕሪል',
        5 => 'ሜይ', 6 => 'ጁን', 7 => 'ጁላይ', 8 => 'ኦገስት',
        9 => 'ሴፕቴምበር', 10 => 'ኦክቶበር', 11 => 'ኖቬምበር', 12 => 'ዲሴምበር',
    ],
    'weekdays' => [
        0 => 'እሑድ', 1 => 'ሰኞ', 2 => 'ማክሰኞ', 3 => 'ረቡዕ',
        4 => 'ሐሙስ', 5 => 'ዓርብ', 6 => 'ቅዳሜ',
    ],
    'era_ethiopian' => 'ዓ.ም.',
    'era_gregorian' => 'እ.ኤ.አ.',
    'period_morning' => 'ጠዋት',
    'period_afternoon' => 'ከሰዓት',
    'period_evening' => 'ምሽት',
    'period_night' => 'ሌሊት',
];
