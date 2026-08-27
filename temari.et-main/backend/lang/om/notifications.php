<?php

return [
    'statuses' => [
        'approved' => 'hayyamameera',
        'rejected' => 'kufaa taasifameera',
        'accepted' => 'fudhatameera',
        'declined' => 'hin fudhatamne',
        'cancelled' => 'haqameera',
        'reopened' => 'irra deebiʼamee banameera',
        'printed' => 'maxxanfameera',
        'delivered' => 'kennameera',
        'ready' => 'qophaaʼeera',
    ],

    'security' => [
        'new_device' => [
            'title' => 'Seensa haaraa gara herrega keetii',
            'body' => 'Herregni Temari.et kee :device irraa seename. Si miti taanaan, jecha darbii kee amma jijjiiri.',
            'sms' => 'Temari.et: herregni kee :device irraa seename. Si miti taanaan, jecha darbii kee battalumatti jijjiiri.',
        ],
        'password_changed' => [
            'title' => 'Jechi darbii kee jijjiirameera',
            'body' => 'Jechi darbii herrega Temari.et keetii jijjiirameera. Si miti taanaan, battalumatti mana barumsaa kee qunnami.',
            'sms' => 'Temari.et: jechi darbii herrega keetii jijjiirameera. Si miti taanaan, mana barumsaa kee battalumatti qunnami.',
        ],
    ],

    'finance' => [
        'invoice_issued' => [
            'title' => 'Nagahee haaraa :student-f',
            'body' => ':fee — Qarshiin :amount ETB :student-f gaafatameera.',
            'sms' => 'Temari.et: nagahee haaraa :student-f — :fee, :amount ETB. Herrega mana barumsaatiin kaffaltee lakkoofsa ragaa Temari.et irratti galchi.',
        ],
        'fee_reminder' => [
            'title' => 'Yaadachiisa kaffaltii :student-f',
            'body' => ':student-f qarshiin :amount ETB hin kaffalamne jira. Adabbii hambisuuf mee kaffali.',
        ],
        'fee_notice' => [
            'title' => 'Beeksisa kaffaltii :student-f',
            'body' => ':fee — :amount ETB :student ilaallata.',
        ],
        'payment_received' => [
            'title' => 'Kaffaltiin fudhatameera — nagaheen qophaaʼeera',
            'body' => ':student-f qarshiin :amount ETB fudhatameera. Nagaheen seera-qabeessi buufachuuf qophaaʼeera.',
        ],
        'payment_verified' => [
            'title' => 'Kaffaltiin mirkanaaʼeera',
            'body' => 'Kaffaltiin :amount ETB :student-f galchite mirkanaaʼeera. Galatoomi.',
            'sms' => 'Temari.et: kaffaltiin :amount ETB :student-f galchite mirkanaaʼeera. Galatoomi.',
        ],
        'payment_rejected' => [
            'title' => 'Kaffaltiin mirkanaaʼuu hin dandeenye',
            'body' => 'Kaffaltiin :student-f galchite (:amount ETB) mirkanaaʼuu hin dandeenye. Mee ilaaltee irra deebiʼii galchi.',
            'sms' => 'Temari.et: kaffaltiin :student-f galchite (:amount ETB) mirkanaaʼuu hin dandeenye. Temari.et irratti ilaali.',
        ],
        'concession_granted' => [
            'title' => 'Iskoolaarshiippii / hirʼinni hayyamameera',
            'body' => ':student-f hirʼinni kaffaltii hayyamameera.',
            'all_children' => 'ijoollee kee hunda',
        ],
        'payment_submitted' => [
            'title' => 'Kaffaltii mirkaneessa eeggatu',
            'body' => 'Maatiin tokko :student-f kaffaltii :amount ETB galcheera. Mirkaneessa kee eeggata.',
        ],
        'concession_suggested' => [
            'title' => 'Yaada hirʼina kaffaltii ilaalamu qabu',
            'body' => 'Yaadni hirʼina kaffaltii :student-f dhiyaate mirkaneessa kee eeggata.',
        ],
        'expense_submitted' => [
            'title' => 'Baasii hayyama eeggatu',
            'body' => ':title — :amount ETB dhiyaateera; murtii barbaada.',
        ],
        'expense_decided' => [
            'title' => 'Baasiin :status',
            'body' => 'Baasiin kee ":title" (:amount ETB) :status taʼeera.',
        ],
    ],

    'attendance' => [
        'absent' => [
            'title' => ':student hin dhufne',
            'body' => ':student guyyaa :date barumsa irraa hafuun galmaaʼeera.',
        ],
        'late' => [
            'title' => ':student turee dhufe',
            'body' => ':student guyyaa :date turee dhufuun galmaaʼeera.',
        ],
        'excuse_filed' => [
            'title' => 'Iyyata hafuu — :student',
            'body' => 'Guddiftuun :student\'f iyyata hafuu dhiyeesse (:from – :to). Fuula hordoffii irratti ilaalaa.',
        ],
        'excuse_decided' => [
            'title' => 'Iyyata hafuu :decision',
            'body' => 'Iyyanni hafuu :student (:from – :to) :decision ta\'eera.',
        ],
    ],

    'academics' => [
        'term_results_published' => [
            'title' => 'Kaardiin gabaasaa qophaaʼeera — :student',
            'body' => 'Kaardiin gabaasaa :term kan :student ilaaluuf qophaaʼeera.',
        ],
        'enrollment_activated' => [
            'title' => 'Galmeen mirkanaaʼeera',
            'body' => 'Galmeen :student :school irratti mirkanaaʼeera. Baga nagaan dhufte!',
            'sms' => 'Temari.et: galmeen :student :school irratti mirkanaaʼeera. Baga nagaan dhufte!',
        ],
        'enrollment_reverted' => [
            'title' => 'Sirreeffama galmee',
            'body' => ':school murtii guddina kutaa waggaa sirreesseera: galmeen :student kan :year haqamee galmeen kutaa duraanii deebiʼee hojii irra jira. Gaaffii yoo qabaattan mana barumsaa qunnamaa.',
        ],
        'timetable_published' => [
            'title' => 'Sagantaan yeroo baʼeera',
            'body' => 'Sagantaan daree :term maxxanfameera.',
        ],
        'section_assigned' => [
            'title' => 'Jijjiirama ramaddii daree',
            'body' => ':student amma :grade — daree :section keessa ramadameera.',
        ],
        'marklist_submitted' => [
            'title' => 'Galmeen qabxii hayyama eeggatu',
            'body' => ':teacher galmee qabxii :subject kan :section galcheera.',
        ],
        'marklist_decided' => [
            'title' => 'Galmeen qabxii :status',
            'body' => 'Galmeen qabxii :subject kan :section kee :status taʼeera.',
        ],
        'marklist_assist' => [
            'title' => 'Qabxiin bakka kee galmaaʼaa jira',
            'body' => ':supervisor galmee qabxii :subject kan :section kee irratti qabxii galchaa jira. Sababa: :reason. Galmeen hundi maqaa isaaniitiin mallatteeffameera — osoo hin galchin dura ilaali.',
        ],
        'marklist_reminder' => [
            'title' => 'Yaadachiisa galmee qabxii',
            'body' => 'Maaloo galmee qabxii :pending kan :term hin dhiyaatin xumurtee galchi: :classes.',
        ],
        'annual_plan_submitted' => [
            'title' => 'Karoorri barnootaa waggaa gamaaggama eeggatu',
            'body' => ':teacher karoora waggaa :subject kan :grade galcheera.',
        ],
        'annual_plan_decided' => [
            'title' => 'Karoorri barnootaa waggaa :status',
            'body' => 'Karoorri waggaa :subject kan :grade kee :status taʼeera.',
        ],
        'weekly_plan_submitted' => [
            'title' => 'Karoorri barnootaa torban gamaaggama eeggatu',
            'body' => ':teacher karoora :subject (:grade) torban :week galcheera.',
        ],
        'weekly_plan_decided' => [
            'title' => 'Karoorri barnootaa torban :status',
            'body' => 'Karoorri :subject (:grade) torban :week kee :status taʼeera.',
        ],
    ],

    'lms' => [
        'assignment_published' => [
            'title' => 'Hojii manaa haaraa — :subject',
            'body' => '":title" kennameera. Guyyaan dhiyeessii :due.',
        ],
        'assignment_graded' => [
            'title' => 'Hojiin manaa qabxii argateera',
            'body' => 'Hojiin kee ":title" irratti qabxii argateera.',
        ],
        'submission_received' => [
            'title' => 'Dhiyeessii haaraa :count',
            'body' => 'Barattoonni :count ":title" dhiyeessaniiru.',
        ],
        'quiz_published' => [
            'title' => ':kind haaraa — :subject',
            'body' => '":title" qabameera. Guyyaa mirkaneeffadhuutii qophaaʼi.',
        ],
        'material_published' => [
            'title' => 'Meeshaa barnootaa haaraa — :subject',
            'body' => '":title" daree keetiif qoodameera.',
        ],
        'thread_reply' => [
            'title' => 'Deebii haaraa',
            'body' => '":title" irratti deebiin haaraan jira.',
        ],
    ],

    'chat' => [
        'message' => [
            'title' => 'Ergaa haaraa :count',
            'body' => ':sender: :preview',
        ],
        'mention' => [
            'title' => ':sender si kaase',
            'body' => ':preview',
        ],
        'emergency' => [
            'title' => 'Beeksisa — :channel',
            'body' => ':preview',
            'sms' => 'Temari.et — :channel: :preview',
        ],
        'approval_pending' => [
            'title' => 'Ergaan :count hayyama eegaa jira',
            'body' => 'Ergaawwan :sender gara maatiitti ergaman gamaaggama kee eegaa jiru.',
        ],
        'message_decided' => [
            'title' => 'Ergaa :status',
            'body' => 'Ergaan gara maatiitti ergite daayirektaraan :status ta\'eera.',
        ],
    ],

    'movement' => [
        'transfer_requested' => [
            'title' => 'Jijjiirraan gaafatameera — :student',
            'body' => 'Jijjiirraan :student :from irraa gara :to gaafatameera. Kan hin eegne taanaan, amma mana barumsaa qunnami.',
        ],
        'transfer_approved' => [
            'title' => 'Jijjiirraan hayyamameera — :student',
            'body' => 'Jijjiirraan :student :from irraa gara :to hayyamameera.',
        ],
        'transfer_rejected' => [
            'title' => 'Jijjiirraan kufeera — :student',
            'body' => 'Gaaffiin jijjiirraa :student gara :to :from-n kufaa taasifameera.',
        ],
        'transfer_cancelled' => [
            'title' => 'Jijjiirraan haqameera — :student',
            'body' => 'Gaaffiin jijjiirraa :student gara :to haqameera.',
        ],
        'withdrawal' => [
            'title' => ':student gadhiifameera',
            'body' => ':student :from irraa gadhiifameera. Xalayaan gadhiifamaa mana barumsaatti argama.',
        ],
        'application_decided' => [
            'title' => 'Iyyanni jijjiirraa :status',
            'body' => 'Iyyanni jijjiirraa :student gara :to galchite :status taʼeera.',
        ],
        'transfer_action_needed' => [
            'title' => 'Jijjiirraa murtii kee eeggatu',
            'body' => ':to :student gaafateera — hayyamuun bilisa taʼuu kaffaltii mirkaneessa.',
        ],
        'application_received' => [
            'title' => 'Iyyata jijjiirraa haaraa',
            'body' => 'Maatiin tokko :student gara mana barumsaa keetii jijjiiruuf iyyateera.',
        ],
    ],

    'inventory' => [
        'requisition_submitted' => [
            'title' => 'Gaaffii meeshaa ilaalamu qabu',
            'body' => ':requester kuusaa irraa meeshaalee gaafateera. Murtoo kee barbaada.',
        ],
        'requisition_decided' => [
            'title' => 'Gaaffiin meeshaa :status',
            'body' => 'Gaaffiin meeshaa keetii :status ta\'eera.',
        ],
        'requisition_issued' => [
            'title' => 'Meeshaaleen qophaa\'aniiru',
            'body' => 'Kuusaan gaaffii kee irratti meeshaalee kenneera — eegduu kuusaa irraa fudhadhu.',
        ],
        'po_submitted' => [
            'title' => 'Ajaja bittaa ilaalamu qabu',
            'body' => 'Ajajni bittaa :supplier dhiyaateera; murtoo barbaada.',
        ],
        'po_decided' => [
            'title' => 'Ajajni bittaa :status',
            'body' => 'Ajajni bittaa :supplier dhiyeessite :status ta\'eera.',
        ],
        'low_stock' => [
            'title' => 'Kuusaa gadi aanaa: :item',
            'body' => ':item irraa :quantity :unit qofatu hafe — guutuun barbaachisa.',
        ],
        'asset_assigned' => [
            'title' => 'Qabeenyi maqaa keetiin galmaa\'e',
            'body' => ':item (mallattoo :tag) amma maqaa keetiin galmaa\'eera. Hanga kuusaatti deebiftutti itti gaafatamummaan kan kee ti.',
        ],
        'textbook_issued' => [
            'title' => 'Kitaabni :student f kenname',
            'body' => ':student «:item» mana barumsaa irraa fudhateera. Dhuma waggaatti waan deebi\'uuf haala gaariin akka turu gargaari.',
        ],
        'textbook_lost' => [
            'title' => 'Kitaaba bade — :student',
            'body' => '«:item» :student f kenname akka badetti galmaa\'eera. Waa\'ee bakka buusaa waajjira mana barumsaa qunnami.',
        ],
    ],

    'hr' => [
        'leave_submitted' => [
            'title' => 'Gaaffii boqonnaa ilaalamu qabu',
            'body' => ':name boqonnaa :type (:days guyyaa) gaafateera.',
        ],
        'leave_decided' => [
            'title' => 'Gaaffiin boqonnaa :status',
            'body' => 'Gaaffiin boqonnaa :type kee :status taʼeera.',
        ],
        'payslip_ready' => [
            'title' => 'Nagaheen mindaa qophaaʼeera',
            'body' => 'Nagaheen mindaa kee kan :period ilaaluuf qophaaʼeera.',
        ],
        'evaluation_shared' => [
            'title' => 'Madaalliin raawwii hojii ergameera',
            'body' => 'Madaalliin raawwii hojii kee kan :term qophaaʼeera — qabxii waliigalaa :score/100. Maaloo ilaaliitii mirkaneessi.',
        ],
        'evaluation_acknowledged' => [
            'title' => 'Madaalliin mirkanaaʼeera',
            'body' => ':teacher madaallii raawwii hojii isaanii kan :term mirkaneessaniiru.',
        ],
    ],

    'family' => [
        'child_registered' => [
            'title' => ':student galmaaʼeera',
            'body' => ':student :school irratti galmaaʼee herrega kee waliin walqabsiifameera.',
        ],
        'guardian_linked' => [
            'title' => 'Guddisaan dabalameera',
            'body' => 'Galmee :student irratti guddisaan walqabsiifameera.',
        ],
        'account_link_decided' => [
            'title' => 'Gaaffiin herregaa :status',
            'body' => 'Gaaffiin eenyummeessaa barataa :public_id walqabsiisuuf galchite :status taʼeera.',
            'sms' => 'Temari.et: gaaffiin eenyummeessaa barataa kee (:public_id) :status taʼeera.',
        ],
        'account_link_requested' => [
            'title' => 'Gaaffii eenyummeessaa barataa ilaalamu qabu',
            'body' => 'Namni tokko :student dha jedheera. Gaaffii walqabsiisa herregaa ilaali.',
        ],
        'card_request_decided' => [
            'title' => 'Haala kaardii eenyummaa',
            'body' => 'Gaaffiin kaardii eenyummaa :name amma :status dha.',
        ],
    ],

    'tutoring' => [
        'application_approved' => [
            'title' => 'Amma barsiisaa dhuunfaa Temari.et taateetta',
            'body' => 'Iyyanni barsiisaa dhuunfaa kee mirkanaaʼeera — piroofaayiliin kee galmee keessatti mulʼateera. Baga nagaan dhufte!',
            'sms' => 'Temari.et: iyyanni barsiisaa dhuunfaa kee MIRKANAAʼEERA. Piroofaayiliin kee mulʼateera — gaaffiiwwan bulchuuf seeni.',
        ],
        'application_declined' => [
            'title' => 'Iyyanni barsiisaa dhuunfaa kufeera',
            'body' => 'Iyyanni kee kufeera: :reason. Iyyata kee fooyyessitee irra deebitee galchuu dandeessa.',
        ],
        'profile_suspended' => [
            'title' => 'Piroofaayiliin barsiisaa dhuunfaa dhaabbateera',
            'body' => 'Piroofaayiliin kee dhaabbateera: :reason. Bal\'inaaf deeggarsa Temari.et qunnami.',
        ],
        'request_received' => [
            'title' => 'Gaaffii barsiisuu haaraa',
            'body' => ':name si qacaruu barbaadu. Sanduuqa gaaffiiwwan keetii irraa ilaaltee deebisi.',
            'sms' => 'Temari.et: gaaffii barsiisuu haaraa qabda. Deebisuuf seeni.',
        ],
        'request_accepted' => [
            'title' => 'Barsiisaan gaaffii kee fudhateera',
            'body' => ':name gaaffii barsiisuu kee fudhateera. Barnoonni akka jalqabuuf ji\'a jalqabaa kaffali.',
            'sms' => 'Temari.et: gaaffiin barsiisuu kee fudhatameera. Barnoota jalqabuuf ji\'a jalqabaa kaffali.',
        ],
        'request_declined' => [
            'title' => 'Gaaffiin barsiisuu kufeera',
            'body' => ':name gaaffii kee hin fudhanne. Galmee keessaa barsiistota biroo ilaali.',
        ],
        'engagement_ended' => [
            'title' => 'Waliigalteen barsiisuu xumurameera',
            'body' => ':name waliigaltee barsiisuu xumureera.',
        ],
        'cycle_due' => [
            'title' => 'Kaffaltiin ji\'a barsiisuu gaʼeera',
            'body' => ':label :tutor waliin — qarshii :amount kaffalamuu qaba. Ji\'ichi yommuu kaffalamu barnoonni jalqaba.',
            'sms' => 'Temari.et: barsiisuu :label :tutor waliin — qarshii :amount kaffalamuu qaba. Barnoota jalqabuuf appii keessatti kaffali.',
        ],
        'cycle_funded' => [
            'title' => 'Ji\'ichi kaffalameera — barsiisuu dandeessa',
            'body' => ':label kaffalamee Temari.et biratti qabameera. Yeroo barnootaa kee karoorfadhu.',
            'sms' => 'Temari.et: barsiisuun :label KAFFALAMEERA (amaanaadhaan qabameera). Amma yeroo barnootaa karoorfachuu dandeessa.',
        ],
        'cycle_released' => [
            'title' => 'Galiin kee gadhiifameera',
            'body' => ':label xumurameera — qarshiin :amount boorsaa kee keessatti dabalameera.',
            'sms' => 'Temari.et: qarshiin :amount kan :label boorsaa barsiisaa kee keessatti gadhiifameera.',
        ],
        'session_scheduled' => [
            'title' => 'Yeroon barnootaa qabameera',
            'body' => 'Barsiisaan kee :when tiif yeroo barnootaa qabateera.',
        ],
        'session_logged' => [
            'title' => 'Yeroo barnootaa kee mirkaneessi',
            'body' => 'Barsiisaan kee barnoota sa\'aatii :hours galmeesseera. Sa\'aatii 72 keessatti mirkaneessi (yookiin komii dhiyeessi).',
        ],
        'session_disputed' => [
            'title' => 'Yeroo barnootaa irratti komiin dhiyaateera',
            'body' => 'Maatiin tokko barnoota galmeessite irratti komii dhiyeesseera. Temari.et ni ilaala.',
        ],
        'payout_paid' => [
            'title' => 'Kaffaltiin ergameera',
            'body' => 'Kaffaltiin kee qarshii :amount gara herrega keetti ergameera.',
            'sms' => 'Temari.et: kaffaltiin barsiisaa kee qarshii :amount gara herrega keetti ergameera.',
        ],
        'review_received' => [
            'title' => 'Madaallii haaraa',
            'body' => 'Maatiin tokko :rating/5 siif kenneera. Piroofaayilii kee ifaa ilaali.',
        ],
    ],

    'system' => [
        'timetable_generated' => [
            'title' => 'Qopheessuun sagantaa xumurameera',
            'body' => 'Wixineen sagantaa :term ilaaluu fi sirreessuuf qophaaʼeera.',
        ],
        'term_results_computed' => [
            'title' => 'Buʼaan boqonnaa herregameera',
            'body' => 'Buʼaan :term cufameera — kaardiiwwan gabaasaa qophaaʼaniiru.',
        ],
        'student_import_completed' => [
            'title' => 'Galchuun barattootaa xumurameera',
            'body' => ':file — barattoonni :imported galfamaniiru, :failed hin milkoofne. Bal\'ina isaa ilaaluuf gabaasa bani.',
        ],
    ],
    // ── Temari AI ──
    'ai' => [
        'weekly_briefing' => [
            'title' => 'Gabaasa AI mana barumsaa kee kan torbanii',
            'body' => ':summary',
        ],
        'parent_digest' => [
            'title' => 'Haala :student kan torban kanaa',
            'body' => ':summary',
        ],
    ],
];
