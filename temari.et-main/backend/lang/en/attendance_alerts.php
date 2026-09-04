<?php

/**
 * Attendance alerts to guardians (SMS + email). Keep SMS to ONE segment —
 * schools pay per message. No calendar dates in the copy ("today" is
 * unambiguous and dodges the Gregorian/Ethiopian calendar question in a text
 * message); alerts are same-day only by design.
 */
return [
    'absent_sms' => ':student was marked absent at :school today. Please contact the school if this is unexpected.',
    'late_sms' => ':student arrived late at :school today (checked in :time).',

    'absent_mail_subject' => ':student absent today — :school',
    'absent_mail_body' => ':student was marked absent at :school today. If this is unexpected, please contact the school. You can review full attendance in Temari.et.',
    'late_mail_subject' => ':student late today — :school',
    'late_mail_body' => ':student arrived late at :school today (checked in :time). You can review full attendance in Temari.et.',

    // 12-hour clock labels for :time.
    'time_am' => 'AM',
    'time_pm' => 'PM',
];
