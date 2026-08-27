<?php

/** Self-signup + account-link SMS. Keep bodies single-SMS short. */
return [
    'throttle' => 'Too many attempts. Please try again in :seconds seconds.',
    'signup_otp_sms' => 'Your Temari.et verification code is :code. It expires in 10 minutes.',
    // PIN reset OTPs — the guardian variant lands on the PARENT's phone for a
    // phone-less ID-login student, so it must name the child.
    'reset_otp_sms' => 'Your Temari.et PIN reset code is :code. It expires in 10 minutes.',
    'reset_otp_guardian_sms' => 'PIN reset code for :student\'s Temari.et student account: :code. It expires in 10 minutes. Ignore this if you did not request it.',
    'account_link_approved_sms' => 'Your school approved your request. Your Temari.et account is now linked to :student. Sign in to see your classes and results.',
];
