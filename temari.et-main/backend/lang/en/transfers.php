<?php

/**
 * Transfer & withdrawal comms (SMS + email). SMS bodies must stay short —
 * many parents are on feature phones and multi-part SMS costs schools money.
 */
return [
    // A school filed a transfer request for the child — also a safety signal:
    // the family learns immediately if anyone tries to move their child.
    'requested_sms' => 'A transfer of :student from :from to :to has been requested. Track it on Temari.et.',
    'approved_sms' => ':student\'s transfer from :from to :to has been approved.',
    'rejected_sms' => ':student\'s transfer request to :to was rejected by :from.',
    'cancelled_sms' => 'The transfer request for :student to :to has been withdrawn.',
    'withdrawal_sms' => ':student has been withdrawn from :from. The clearance letter is available at the school.',

    // Parent/student-initiated applications.
    'application_accepted_sms' => ':to accepted your transfer application for :student. It now awaits approval from :from.',
    'application_declined_sms' => ':to declined the transfer application for :student.',

    'mail_subject' => 'Transfer update for :student',
    'mail_body' => ':message

Sign in to Temari.et to see the full status.',
];
