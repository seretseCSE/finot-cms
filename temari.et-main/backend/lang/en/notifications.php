<?php

/**
 * In-app notification copy — one entry per NotificationCatalog event:
 * `title` + `body` (rendered at READ time in the reader's language, so keep
 * placeholders stable), optional `sms` (used only when the event is on the
 * platform SMS whitelist; events whose bespoke notifier owns the SMS copy —
 * fees, attendance, transfers, registration — point smsKey at those files
 * instead). Placeholders arrive from the Notifier `data` payload.
 */

return [
    // Localized value-words substituted into :status / :type placeholders
    // (NotificationCatalog::localizeParams) so a status stored in English
    // renders in the reader's language.
    'statuses' => [
        'approved' => 'approved',
        'rejected' => 'rejected',
        'accepted' => 'accepted',
        'declined' => 'declined',
        'cancelled' => 'cancelled',
        'reopened' => 'reopened',
        'printed' => 'printed',
        'delivered' => 'delivered',
        'ready' => 'ready',
    ],

    // ── Security ───────────────────────────────────────────────────────────
    'security' => [
        'new_device' => [
            'title' => 'New sign-in to your account',
            'body' => 'Your Temari.et account was signed in from :device. If this was not you, change your password now.',
            'sms' => 'Temari.et: new sign-in to your account from :device. Not you? Change your password immediately.',
        ],
        'password_changed' => [
            'title' => 'Your password was changed',
            'body' => 'The password for your Temari.et account was just changed. If this was not you, contact your school immediately.',
            'sms' => 'Temari.et: your account password was changed. Not you? Contact your school immediately.',
        ],
    ],

    // ── Finance ────────────────────────────────────────────────────────────
    'finance' => [
        'invoice_issued' => [
            'title' => 'New invoice for :student',
            'body' => ':fee — :amount ETB is due for :student.',
            'sms' => 'Temari.et: new invoice for :student — :fee, :amount ETB. Pay via your school\'s account and submit the reference on Temari.et.',
        ],
        'fee_reminder' => [
            'title' => 'Payment reminder for :student',
            'body' => ':amount ETB is outstanding for :student. Please settle it to avoid penalties.',
        ],
        'fee_notice' => [
            'title' => 'Fee notice for :student',
            'body' => ':fee — :amount ETB applies to :student.',
        ],
        'payment_received' => [
            'title' => 'Payment received — receipt ready',
            'body' => ':amount ETB received for :student. The official receipt is ready to download.',
        ],
        'payment_verified' => [
            'title' => 'Payment verified',
            'body' => 'Your payment of :amount ETB for :student has been verified. Thank you.',
            'sms' => 'Temari.et: your payment of :amount ETB for :student is verified. Thank you.',
        ],
        'payment_rejected' => [
            'title' => 'Payment could not be verified',
            'body' => 'The payment you submitted for :student (:amount ETB) could not be verified. Please review and resubmit.',
            'sms' => 'Temari.et: the payment you submitted for :student (:amount ETB) could not be verified. Please review it on Temari.et.',
        ],
        'concession_granted' => [
            'title' => 'Scholarship / discount granted',
            'body' => 'A fee concession was granted for :student.',
            'all_children' => 'all your children',
        ],
        'payment_submitted' => [
            'title' => 'Payment awaiting verification',
            'body' => 'A family submitted a payment of :amount ETB for :student. It needs your verification.',
        ],
        'concession_suggested' => [
            'title' => 'Concession suggestion to review',
            'body' => 'A fee concession for :student awaits your approval.',
        ],
        'expense_submitted' => [
            'title' => 'Expense awaiting approval',
            'body' => ':title — :amount ETB was submitted and needs a decision.',
        ],
        'expense_decided' => [
            'title' => 'Expense :status',
            'body' => 'Your expense ":title" (:amount ETB) was :status.',
        ],
    ],

    // ── Attendance ─────────────────────────────────────────────────────────
    'attendance' => [
        'absent' => [
            'title' => ':student was absent',
            'body' => ':student was marked absent on :date.',
        ],
        'late' => [
            'title' => ':student arrived late',
            'body' => ':student was marked late on :date.',
        ],
        'excuse_filed' => [
            'title' => 'Absence excuse — :student',
            'body' => 'A guardian filed an absence excuse for :student (:from – :to). Review it in Attendance.',
        ],
        'excuse_decided' => [
            'title' => 'Absence excuse :decision',
            'body' => 'The excuse for :student (:from – :to) was :decision.',
        ],
    ],

    // ── Academics ──────────────────────────────────────────────────────────
    'academics' => [
        'term_results_published' => [
            'title' => 'Report card ready — :student',
            'body' => 'The :term report card for :student is ready to view.',
        ],
        'enrollment_activated' => [
            'title' => 'Enrollment confirmed',
            'body' => ':student\'s enrollment at :school is now active. Welcome!',
            'sms' => 'Temari.et: :student\'s enrollment at :school is confirmed. Welcome!',
        ],
        'enrollment_reverted' => [
            'title' => 'Enrollment correction',
            'body' => ':school corrected a year-end promotion: :student\'s :year enrollment was removed and the previous class record is active again. Contact the school with any questions.',
        ],
        'timetable_published' => [
            'title' => 'Timetable published',
            'body' => 'The class timetable for :term has been published.',
        ],
        'section_assigned' => [
            'title' => 'Class section update',
            'body' => ':student is now in :grade — section :section.',
        ],
        'marklist_submitted' => [
            'title' => 'Marklist awaiting approval',
            'body' => ':teacher submitted the :subject marklist for :section.',
        ],
        'marklist_decided' => [
            'title' => 'Marklist :status',
            'body' => 'Your :subject marklist for :section was :status.',
        ],
        'marklist_assist' => [
            'title' => 'Marks entered on your behalf',
            'body' => ':supervisor is entering marks in your :subject marklist for :section. Reason: :reason. Every entry is labeled with their name — review before you submit.',
        ],
        'marklist_reminder' => [
            'title' => 'Marklist reminder',
            'body' => 'Please finish and submit your :pending pending marklist(s) for :term: :classes.',
        ],
        'annual_plan_submitted' => [
            'title' => 'Annual lesson plan awaiting review',
            'body' => ':teacher submitted the :subject annual plan for :grade.',
        ],
        'annual_plan_decided' => [
            'title' => 'Annual lesson plan :status',
            'body' => 'Your :subject annual plan for :grade was :status.',
        ],
        'weekly_plan_submitted' => [
            'title' => 'Weekly lesson plan awaiting review',
            'body' => ':teacher submitted the :subject (:grade) plan for the week of :week.',
        ],
        'weekly_plan_decided' => [
            'title' => 'Weekly lesson plan :status',
            'body' => 'Your :subject (:grade) plan for the week of :week was :status.',
        ],
    ],

    // ── LMS ────────────────────────────────────────────────────────────────
    'lms' => [
        'assignment_published' => [
            'title' => 'New assignment — :subject',
            'body' => '":title" was assigned. Due :due.',
        ],
        'assignment_graded' => [
            'title' => 'Assignment graded',
            'body' => 'Your work on ":title" has been graded.',
        ],
        'submission_received' => [
            'title' => ':count new submission(s)',
            'body' => ':count student(s) submitted ":title".',
        ],
        'quiz_published' => [
            'title' => 'New :kind — :subject',
            'body' => '":title" is scheduled. Check the date and be prepared.',
        ],
        'material_published' => [
            'title' => 'New material — :subject',
            'body' => '":title" was shared with your class.',
        ],
        'thread_reply' => [
            'title' => 'New reply',
            'body' => 'New reply on ":title".',
        ],
    ],

    // ── Chat (ADR-019) ─────────────────────────────────────────────────────
    'chat' => [
        'message' => [
            'title' => ':count new message(s)',
            'body' => ':sender: :preview',
        ],
        'mention' => [
            'title' => ':sender mentioned you',
            'body' => ':preview',
        ],
        'emergency' => [
            'title' => 'Notice — :channel',
            'body' => ':preview',
            'sms' => 'Temari.et — :channel: :preview',
        ],
        'approval_pending' => [
            'title' => ':count message(s) awaiting approval',
            'body' => 'Messages from :sender to families are waiting for your review.',
        ],
        'message_decided' => [
            'title' => 'Message :status',
            'body' => 'Your message to the family was :status by the director.',
        ],
    ],

    // ── Student movement ───────────────────────────────────────────────────
    'movement' => [
        'transfer_requested' => [
            'title' => 'Transfer requested — :student',
            'body' => 'A transfer of :student from :from to :to was requested. If this is unexpected, contact the school now.',
        ],
        'transfer_approved' => [
            'title' => 'Transfer approved — :student',
            'body' => ':student\'s transfer from :from to :to was approved.',
        ],
        'transfer_rejected' => [
            'title' => 'Transfer rejected — :student',
            'body' => ':student\'s transfer request to :to was rejected by :from.',
        ],
        'transfer_cancelled' => [
            'title' => 'Transfer cancelled — :student',
            'body' => 'The transfer request for :student to :to was cancelled.',
        ],
        'withdrawal' => [
            'title' => ':student withdrawn',
            'body' => ':student was withdrawn from :from. The clearance letter is available at the school.',
        ],
        'application_decided' => [
            'title' => 'Transfer application :status',
            'body' => 'Your transfer application for :student to :to was :status.',
        ],
        'transfer_action_needed' => [
            'title' => 'Transfer needs your decision',
            'body' => ':to requests :student — approving confirms fee clearance.',
        ],
        'application_received' => [
            'title' => 'New transfer application',
            'body' => 'A family applied to transfer :student to your school.',
        ],
    ],

    // ── Inventory & school property ─────────────────────────────────────────
    'inventory' => [
        'requisition_submitted' => [
            'title' => 'Store request to review',
            'body' => ':requester asked the store for items. It needs your decision.',
        ],
        'requisition_decided' => [
            'title' => 'Store request :status',
            'body' => 'Your store request was :status.',
        ],
        'requisition_issued' => [
            'title' => 'Items ready for pickup',
            'body' => 'The store has issued items on your request — collect them from the storekeeper.',
        ],
        'po_submitted' => [
            'title' => 'Purchase order to review',
            'body' => 'A purchase order for :supplier was raised and needs a decision.',
        ],
        'po_decided' => [
            'title' => 'Purchase order :status',
            'body' => 'Your purchase order for :supplier was :status.',
        ],
        'low_stock' => [
            'title' => 'Low stock: :item',
            'body' => 'Only :quantity :unit of :item remain in the store — time to restock.',
        ],
        'asset_assigned' => [
            'title' => 'Property assigned to you',
            'body' => ':item (tag :tag) is now registered in your name. You are its custodian until you return it to the store.',
        ],
        'textbook_issued' => [
            'title' => 'Textbook issued to :student',
            'body' => ':student received ":item" from the school. Please help keep it in good condition — it returns at year end.',
        ],
        'textbook_lost' => [
            'title' => 'Textbook lost — :student',
            'body' => 'The school recorded ":item" issued to :student as lost. Contact the school office about a replacement.',
        ],
    ],

    // ── HR ─────────────────────────────────────────────────────────────────
    'hr' => [
        'leave_submitted' => [
            'title' => 'Leave request to review',
            'body' => ':name requested :type leave (:days day(s)).',
        ],
        'leave_decided' => [
            'title' => 'Leave request :status',
            'body' => 'Your :type leave request was :status.',
        ],
        'payslip_ready' => [
            'title' => 'Payslip ready',
            'body' => 'Your payslip for :period is ready to view.',
        ],
        'evaluation_shared' => [
            'title' => 'Performance appraisal shared',
            'body' => 'Your :term performance appraisal is ready — overall score :score/100. Please review and acknowledge it.',
        ],
        'evaluation_acknowledged' => [
            'title' => 'Appraisal acknowledged',
            'body' => ':teacher acknowledged their :term performance appraisal.',
        ],
    ],

    // ── Family / accounts ──────────────────────────────────────────────────
    'family' => [
        'child_registered' => [
            'title' => ':student registered',
            'body' => ':student was registered at :school and linked to your account.',
        ],
        'guardian_linked' => [
            'title' => 'Guardian added',
            'body' => 'A guardian was linked to :student\'s record.',
        ],
        'account_link_decided' => [
            'title' => 'Account claim :status',
            'body' => 'Your request to link the student ID :public_id was :status.',
            'sms' => 'Temari.et: your student ID claim (:public_id) was :status.',
        ],
        'account_link_requested' => [
            'title' => 'Student ID claim to review',
            'body' => 'Someone claims to be :student. Review the account link request.',
        ],
        'card_request_decided' => [
            'title' => 'ID card update',
            'body' => 'The ID card request for :name is now :status.',
        ],
    ],

    // ── Tutoring marketplace ────────────────────────────────────────────────
    'tutoring' => [
        'application_approved' => [
            'title' => 'You are now a Temari.et tutor',
            'body' => 'Your tutor application was approved — your profile is live in the directory. Welcome aboard!',
            'sms' => 'Temari.et: your tutor application is APPROVED. Your profile is now live — sign in to manage requests.',
        ],
        'application_declined' => [
            'title' => 'Tutor application declined',
            'body' => 'Your application was declined: :reason. You can update your application and submit again.',
        ],
        'profile_suspended' => [
            'title' => 'Tutor profile suspended',
            'body' => 'Your tutor profile was suspended: :reason. Contact Temari.et support for details.',
        ],
        'request_received' => [
            'title' => 'New tutoring request',
            'body' => ':name wants to hire you. Review and respond from your requests inbox.',
            'sms' => 'Temari.et: you have a new tutoring request. Sign in to respond.',
        ],
        'request_accepted' => [
            'title' => 'Tutor accepted your request',
            'body' => ':name accepted your tutoring request. Pay the first month to start the sessions.',
            'sms' => 'Temari.et: your tutoring request was accepted. Pay the first month to start lessons.',
        ],
        'request_declined' => [
            'title' => 'Tutoring request declined',
            'body' => ':name declined your tutoring request. Browse other tutors in the directory.',
        ],
        'engagement_ended' => [
            'title' => 'Tutoring engagement ended',
            'body' => ':name ended the tutoring engagement.',
        ],
        'cycle_due' => [
            'title' => 'Tutoring month due',
            'body' => ':label with :tutor — :amount ETB is due. Sessions start once the month is paid.',
            'sms' => 'Temari.et: :label tutoring with :tutor — :amount ETB due. Pay in the app to start the month\'s lessons.',
        ],
        'cycle_funded' => [
            'title' => 'Month paid — you can teach',
            'body' => ':label is paid and held by Temari.et. Schedule your sessions.',
            'sms' => 'Temari.et: :label tutoring is PAID (held in escrow). You can now schedule sessions.',
        ],
        'cycle_released' => [
            'title' => 'Earnings released',
            'body' => ':label was settled — :amount ETB was added to your wallet.',
            'sms' => 'Temari.et: :amount ETB for :label was released to your tutor wallet.',
        ],
        'session_scheduled' => [
            'title' => 'Tutoring session scheduled',
            'body' => 'Your tutor scheduled a session for :when.',
        ],
        'session_logged' => [
            'title' => 'Confirm your tutoring session',
            'body' => 'Your tutor logged a :hours hour session. Confirm it (or raise an issue) within 72 hours.',
        ],
        'session_disputed' => [
            'title' => 'A session was disputed',
            'body' => 'A family disputed one of your logged sessions. Temari.et will review it.',
        ],
        'payout_paid' => [
            'title' => 'Payout sent',
            'body' => 'Your payout of :amount ETB was sent to your account.',
            'sms' => 'Temari.et: your tutor payout of :amount ETB was sent to your account.',
        ],
        'review_received' => [
            'title' => 'New review',
            'body' => 'A family rated you :rating/5. See your public profile.',
        ],
    ],

    // ── System ─────────────────────────────────────────────────────────────
    'system' => [
        'timetable_generated' => [
            'title' => 'Timetable generation finished',
            'body' => 'The :term timetable draft is ready to review and tune.',
        ],
        'term_results_computed' => [
            'title' => 'Term results computed',
            'body' => ':term results are frozen — report cards are ready.',
        ],
        'student_import_completed' => [
            'title' => 'Student import finished',
            'body' => ':file — :imported students imported, :failed failed. Open the report for details.',
        ],
    ],
    // ── Temari AI ──
    'ai' => [
        'weekly_briefing' => [
            'title' => 'Your weekly school AI briefing',
            'body' => ':summary',
        ],
        'parent_digest' => [
            'title' => "This week's update on :student",
            'body' => ':summary',
        ],
    ],
];
