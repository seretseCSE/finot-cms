<?php

/**
 * Registration comms (SMS + email). SMS bodies must stay short — many parents
 * are on feature phones and multi-part SMS costs schools money. `:link` in
 * setup messages is replaced by PasswordSetupService.
 */
return [
    // New guardian account → password-setup SMS with context.
    'guardian_setup_sms' => ':student has been registered at :school. Create your Temari.et parent account: :link',

    // Existing account → contextual notice only.
    'guardian_registered_sms' => ':student has been registered at :school. Sign in to Temari.et to follow their progress.',

    // Student with their own account.
    'student_setup_sms' => 'You have been registered at :school. Create your Temari.et account: :link',

    // Phone-less ID login — goes to the PRIMARY GUARDIAN's phone, so it names
    // the child and their sign-in ID explicitly (separate from the guardian's
    // own parent-account messages above).
    'student_id_setup_sms' => ':student can sign in to Temari.et with Student ID :id. Set their PIN here: :link (This is their student login, separate from your parent account.)',
    'student_registered_sms' => 'You have been registered at :school. Sign in to Temari.et to see your classes and results.',

    // Email (guardians with an email address).
    'guardian_registered_mail_subject' => ':student registered at :school',
    'guardian_registered_mail_body' => ':student has been registered at :school on Temari.et. You have been listed as their :relationship. Sign in to follow their attendance, grades and fees.',
];
