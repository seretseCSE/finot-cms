<?php

return [
    'statuses' => [
        'approved' => 'ጸድቋል',
        'rejected' => 'ውድቅ ተደርጓል',
        'accepted' => 'ተቀብሏል',
        'declined' => 'አልተቀበለም',
        'cancelled' => 'ተሰርዟል',
        'reopened' => 'እንደገና ተከፍቷል',
        'printed' => 'ታትሟል',
        'delivered' => 'ተረክቧል',
        'ready' => 'ዝግጁ',
    ],

    'security' => [
        'new_device' => [
            'title' => 'አዲስ መግቢያ ወደ መለያዎ',
            'body' => 'የTemari.et መለያዎ ከ:device ገብቷል። እርስዎ ካልሆኑ አሁኑኑ የይለፍ ቃልዎን ይቀይሩ።',
            'sms' => 'Temari.et፦ መለያዎ ከ:device ገብቷል። እርስዎ ካልሆኑ የይለፍ ቃልዎን ወዲያውኑ ይቀይሩ።',
        ],
        'password_changed' => [
            'title' => 'የይለፍ ቃልዎ ተቀይሯል',
            'body' => 'የTemari.et መለያዎ የይለፍ ቃል ተቀይሯል። እርስዎ ካልሆኑ ወዲያውኑ ትምህርት ቤትዎን ያግኙ።',
            'sms' => 'Temari.et፦ የመለያዎ የይለፍ ቃል ተቀይሯል። እርስዎ ካልሆኑ ትምህርት ቤትዎን ወዲያውኑ ያግኙ።',
        ],
    ],

    'finance' => [
        'invoice_issued' => [
            'title' => 'ለ:student አዲስ ክፍያ መጠየቂያ',
            'body' => ':fee — :amount ብር ለ:student ተጠይቋል።',
            'sms' => 'Temari.et፦ ለ:student አዲስ ክፍያ መጠየቂያ — :fee፣ :amount ብር። በትምህርት ቤቱ አካውንት ከፍለው ማጣቀሻውን በTemari.et ያስገቡ።',
        ],
        'fee_reminder' => [
            'title' => 'የክፍያ ማስታወሻ ለ:student',
            'body' => 'ለ:student :amount ብር ያልተከፈለ አለ። ቅጣት እንዳይኖር እባክዎ ይክፈሉ።',
        ],
        'fee_notice' => [
            'title' => 'የክፍያ ማሳወቂያ ለ:student',
            'body' => ':fee — :amount ብር ለ:student ይመለከታል።',
        ],
        'payment_received' => [
            'title' => 'ክፍያ ተቀብለናል — ደረሰኝ ዝግጁ ነው',
            'body' => 'ለ:student :amount ብር ተቀብለናል። ይፋዊ ደረሰኙ ለማውረድ ዝግጁ ነው።',
        ],
        'payment_verified' => [
            'title' => 'ክፍያ ተረጋግጧል',
            'body' => 'ለ:student ያስገቡት የ:amount ብር ክፍያ ተረጋግጧል። እናመሰግናለን።',
            'sms' => 'Temari.et፦ ለ:student ያስገቡት የ:amount ብር ክፍያ ተረጋግጧል። እናመሰግናለን።',
        ],
        'payment_rejected' => [
            'title' => 'ክፍያው ሊረጋገጥ አልቻለም',
            'body' => 'ለ:student ያስገቡት ክፍያ (:amount ብር) ሊረጋገጥ አልቻለም። እባክዎ አጣርተው እንደገና ያስገቡ።',
            'sms' => 'Temari.et፦ ለ:student ያስገቡት ክፍያ (:amount ብር) ሊረጋገጥ አልቻለም። በTemari.et ላይ ይመልከቱ።',
        ],
        'concession_granted' => [
            'title' => 'ስኮላርሺፕ / ቅናሽ ተፈቅዷል',
            'body' => 'ለ:student የክፍያ ቅናሽ ተፈቅዷል።',
            'all_children' => 'ሁሉም ልጆችዎ',
        ],
        'payment_submitted' => [
            'title' => 'ማረጋገጫ የሚጠብቅ ክፍያ',
            'body' => 'አንድ ቤተሰብ ለ:student የ:amount ብር ክፍያ አስገብቷል። ማረጋገጫዎን ይጠብቃል።',
        ],
        'concession_suggested' => [
            'title' => 'የሚገመገም የቅናሽ ጥቆማ',
            'body' => 'ለ:student የቀረበ የክፍያ ቅናሽ ጥቆማ ማጽደቅዎን ይጠብቃል።',
        ],
        'expense_submitted' => [
            'title' => 'ማጽደቅ የሚጠብቅ ወጪ',
            'body' => ':title — :amount ብር ቀርቧል፤ ውሳኔ ይፈልጋል።',
        ],
        'expense_decided' => [
            'title' => 'ወጪ :status',
            'body' => '":title" (:amount ብር) ወጪዎ :status ሆኗል።',
        ],
    ],

    'attendance' => [
        'absent' => [
            'title' => ':student ቀርቷል/ቀርታለች',
            'body' => ':student በ:date ከትምህርት ቀርቷል/ቀርታለች ተብሎ ተመዝግቧል።',
        ],
        'late' => [
            'title' => ':student አርፍዷል/አርፍዳለች',
            'body' => ':student በ:date አርፍዶ/አርፍዳ መጥቷል/መጥታለች ተብሎ ተመዝግቧል።',
        ],
        'excuse_filed' => [
            'title' => 'የቀሪነት ማመልከቻ — :student',
            'body' => 'አሳዳጊ ለ:student የቀሪነት ማመልከቻ አቅርበዋል (:from – :to)። በክትትል ገጹ ይመልከቱ።',
        ],
        'excuse_decided' => [
            'title' => 'የቀሪነት ማመልከቻ :decision',
            'body' => 'የ:student የቀሪነት ማመልከቻ (:from – :to) ውሳኔ: :decision።',
        ],
    ],

    'academics' => [
        'term_results_published' => [
            'title' => 'ሪፖርት ካርድ ዝግጁ — :student',
            'body' => 'የ:student የ:term ሪፖርት ካርድ ለመታየት ዝግጁ ነው።',
        ],
        'enrollment_activated' => [
            'title' => 'ምዝገባ ጸድቋል',
            'body' => 'የ:student ምዝገባ በ:school ጸድቋል። እንኳን ደህና መጡ!',
            'sms' => 'Temari.et፦ የ:student ምዝገባ በ:school ተረጋግጧል። እንኳን ደህና መጡ!',
        ],
        'enrollment_reverted' => [
            'title' => 'የምዝገባ እርማት',
            'body' => ':school የዓመቱን የክፍል እድገት ውሳኔ አስተካክሏል፦ የ:student የ:year ምዝገባ ተሰርዞ የቀድሞው የክፍል መዝገብ እንደገና ንቁ ሆኗል። ጥያቄ ካለዎት ትምህርት ቤቱን ያነጋግሩ።',
        ],
        'timetable_published' => [
            'title' => 'የክፍለ ጊዜ ሰሌዳ ወጥቷል',
            'body' => 'የ:term የክፍል ሰሌዳ ታትሟል።',
        ],
        'section_assigned' => [
            'title' => 'የክፍል ምደባ ለውጥ',
            'body' => ':student አሁን በ:grade — ክፍል :section ተመድቧል።',
        ],
        'marklist_submitted' => [
            'title' => 'ማጽደቅ የሚጠብቅ የውጤት ዝርዝር',
            'body' => ':teacher የ:section የ:subject የውጤት ዝርዝር አስገብቷል/አስገብታለች።',
        ],
        'marklist_decided' => [
            'title' => 'የውጤት ዝርዝር :status',
            'body' => 'የ:section የ:subject የውጤት ዝርዝርዎ :status ሆኗል።',
        ],
        'marklist_assist' => [
            'title' => 'ውጤት በእርስዎ ምትክ እየተመዘገበ ነው',
            'body' => ':supervisor በ:section የ:subject የውጤት ዝርዝርዎ ላይ ውጤት እየመዘገበ/ች ነው። ምክንያት፦ :reason። እያንዳንዱ ምዝገባ በስማቸው ተለይቷል — ከማስገባትዎ በፊት ይመልከቱ።',
        ],
        'marklist_reminder' => [
            'title' => 'የውጤት ዝርዝር ማስታወሻ',
            'body' => 'እባክዎ ለ:term ያልቀረቡ :pending የውጤት ዝርዝር(ዞች)ዎን ሞልተው ያስገቡ፦ :classes።',
        ],
        'annual_plan_submitted' => [
            'title' => 'ግምገማ የሚጠብቅ ዓመታዊ የትምህርት እቅድ',
            'body' => ':teacher የ:grade የ:subject ዓመታዊ እቅድ አስገብቷል/አስገብታለች።',
        ],
        'annual_plan_decided' => [
            'title' => 'ዓመታዊ የትምህርት እቅድ :status',
            'body' => 'የ:grade የ:subject ዓመታዊ እቅድዎ :status ሆኗል።',
        ],
        'weekly_plan_submitted' => [
            'title' => 'ግምገማ የሚጠብቅ ሳምንታዊ የትምህርት እቅድ',
            'body' => ':teacher የ:subject (:grade) የ:week ሳምንት እቅድ አስገብቷል/አስገብታለች።',
        ],
        'weekly_plan_decided' => [
            'title' => 'ሳምንታዊ የትምህርት እቅድ :status',
            'body' => 'የ:subject (:grade) የ:week ሳምንት እቅድዎ :status ሆኗል።',
        ],
    ],

    'lms' => [
        'assignment_published' => [
            'title' => 'አዲስ አሳይመንት — :subject',
            'body' => '":title" ተሰጥቷል። ማስረከቢያው :due ነው።',
        ],
        'assignment_graded' => [
            'title' => 'አሳይመንት ተመርምሯል',
            'body' => 'የ":title" ስራዎ ውጤት ተሰጥቶታል።',
        ],
        'submission_received' => [
            'title' => ':count አዲስ ርክክብ',
            'body' => ':count ተማሪ(ዎች) ":title"ን አስረክበዋል።',
        ],
        'quiz_published' => [
            'title' => 'አዲስ :kind — :subject',
            'body' => '":title" ተይዟል። ቀኑን አረጋግጠው ይዘጋጁ።',
        ],
        'material_published' => [
            'title' => 'አዲስ የትምህርት ግብዓት — :subject',
            'body' => '":title" ለክፍልዎ ተጋርቷል።',
        ],
        'thread_reply' => [
            'title' => 'አዲስ ምላሽ',
            'body' => 'በ":title" ላይ አዲስ ምላሽ አለ።',
        ],
    ],

    'chat' => [
        'message' => [
            'title' => ':count አዲስ መልዕክት(ዎች)',
            'body' => ':sender: :preview',
        ],
        'mention' => [
            'title' => ':sender ጠቅሶዎታል',
            'body' => ':preview',
        ],
        'emergency' => [
            'title' => 'ማስታወቂያ — :channel',
            'body' => ':preview',
            'sms' => 'Temari.et — :channel: :preview',
        ],
        'approval_pending' => [
            'title' => ':count መልዕክት(ዎች) ፈቃድ ይጠብቃሉ',
            'body' => 'ከ:sender ወደ ቤተሰቦች የተላኩ መልዕክቶች ግምገማዎን ይጠብቃሉ።',
        ],
        'message_decided' => [
            'title' => 'መልዕክት :status',
            'body' => 'ወደ ቤተሰብ የላኩት መልዕክት በዳይሬክተሩ :status ሆኗል።',
        ],
    ],

    'movement' => [
        'transfer_requested' => [
            'title' => 'ዝውውር ተጠይቋል — :student',
            'body' => 'የ:student ዝውውር ከ:from ወደ :to ተጠይቋል። ያልጠበቁት ከሆነ አሁኑኑ ትምህርት ቤቱን ያግኙ።',
        ],
        'transfer_approved' => [
            'title' => 'ዝውውር ጸድቋል — :student',
            'body' => 'የ:student ዝውውር ከ:from ወደ :to ጸድቋል።',
        ],
        'transfer_rejected' => [
            'title' => 'ዝውውር ውድቅ ሆኗል — :student',
            'body' => 'የ:student የዝውውር ጥያቄ ወደ :to በ:from ውድቅ ተደርጓል።',
        ],
        'transfer_cancelled' => [
            'title' => 'ዝውውር ተሰርዟል — :student',
            'body' => 'የ:student የዝውውር ጥያቄ ወደ :to ተነስቷል።',
        ],
        'withdrawal' => [
            'title' => ':student ተሰናብቷል/ታለች',
            'body' => ':student ከ:from ተሰናብቷል/ታለች። የስንብት ደብዳቤው ትምህርት ቤቱ ላይ ይገኛል።',
        ],
        'application_decided' => [
            'title' => 'የዝውውር ማመልከቻ :status',
            'body' => 'የ:student የዝውውር ማመልከቻዎ ወደ :to :status ሆኗል።',
        ],
        'transfer_action_needed' => [
            'title' => 'ውሳኔዎን የሚጠብቅ ዝውውር',
            'body' => ':to :studentን ጠይቋል — ማጽደቅዎ የክፍያ ነጻነትን ያረጋግጣል።',
        ],
        'application_received' => [
            'title' => 'አዲስ የዝውውር ማመልከቻ',
            'body' => 'አንድ ቤተሰብ :studentን ወደ ትምህርት ቤትዎ ለማዛወር አመልክቷል።',
        ],
    ],

    'inventory' => [
        'requisition_submitted' => [
            'title' => 'የሚገመገም የዕቃ ጥያቄ',
            'body' => ':requester ከዕቃ ግምጃ ቤት ዕቃዎችን ጠይቋል/ጠይቃለች። ውሳኔዎን ይፈልጋል።',
        ],
        'requisition_decided' => [
            'title' => 'የዕቃ ጥያቄ :status',
            'body' => 'የዕቃ ጥያቄዎ :status ሆኗል።',
        ],
        'requisition_issued' => [
            'title' => 'ዕቃዎች ተዘጋጅተዋል',
            'body' => 'ግምጃ ቤቱ በጥያቄዎ መሠረት ዕቃዎችን ሰጥቷል — ከንብረት ክፍል ይረከቡ።',
        ],
        'po_submitted' => [
            'title' => 'የሚገመገም የግዢ ትዕዛዝ',
            'body' => 'ለ:supplier የግዢ ትዕዛዝ ቀርቧል፤ ውሳኔ ይፈልጋል።',
        ],
        'po_decided' => [
            'title' => 'የግዢ ትዕዛዝ :status',
            'body' => 'ለ:supplier ያቀረቡት የግዢ ትዕዛዝ :status ሆኗል።',
        ],
        'low_stock' => [
            'title' => 'ዝቅተኛ ክምችት፦ :item',
            'body' => 'ከ:item :quantity :unit ብቻ ቀርቷል — መሙላት ያስፈልጋል።',
        ],
        'asset_assigned' => [
            'title' => 'ንብረት በስምዎ ተመዝግቧል',
            'body' => ':item (መለያ :tag) አሁን በስምዎ ተመዝግቧል። ወደ ግምጃ ቤት እስኪመልሱት ድረስ ኃላፊነቱ የእርስዎ ነው።',
        ],
        'textbook_issued' => [
            'title' => 'ለ:student መጽሐፍ ተሰጥቷል',
            'body' => ':student «:item» ከትምህርት ቤቱ ተቀብሏል/ተቀብላለች። በዓመቱ መጨረሻ ስለሚመለስ በጥሩ ሁኔታ እንዲቆይ ይርዱ።',
        ],
        'textbook_lost' => [
            'title' => 'የጠፋ መጽሐፍ — :student',
            'body' => 'ለ:student የተሰጠው «:item» እንደጠፋ ተመዝግቧል። ስለ ምትክ የትምህርት ቤቱን ቢሮ ያነጋግሩ።',
        ],
    ],

    'hr' => [
        'leave_submitted' => [
            'title' => 'የሚገመገም የፈቃድ ጥያቄ',
            'body' => ':name የ:type ፈቃድ (:days ቀን) ጠይቋል/ጠይቃለች።',
        ],
        'leave_decided' => [
            'title' => 'የፈቃድ ጥያቄ :status',
            'body' => 'የ:type ፈቃድ ጥያቄዎ :status ሆኗል።',
        ],
        'payslip_ready' => [
            'title' => 'የደመወዝ ደረሰኝ ዝግጁ ነው',
            'body' => 'የ:period የደመወዝ ደረሰኝዎ ለመታየት ዝግጁ ነው።',
        ],
        'evaluation_shared' => [
            'title' => 'የስራ አፈጻጸም ምዘና ደርሷል',
            'body' => 'የ:term የስራ አፈጻጸም ምዘናዎ ዝግጁ ነው — አጠቃላይ ውጤት :score/100። እባክዎ ገምግመው ያረጋግጡ።',
        ],
        'evaluation_acknowledged' => [
            'title' => 'ምዘናው ተረጋግጧል',
            'body' => ':teacher የ:term የስራ አፈጻጸም ምዘናቸውን አረጋግጠዋል።',
        ],
    ],

    'family' => [
        'child_registered' => [
            'title' => ':student ተመዝግቧል/ባለች',
            'body' => ':student በ:school ተመዝግቦ/ባ ከመለያዎ ጋር ተያይዟል።',
        ],
        'guardian_linked' => [
            'title' => 'ሞግዚት ተጨምሯል',
            'body' => 'ከ:student መዝገብ ጋር ሞግዚት ተያይዟል።',
        ],
        'account_link_decided' => [
            'title' => 'የመለያ ይገባኛል :status',
            'body' => 'የተማሪ መታወቂያ :public_id ለማገናኘት ያቀረቡት ጥያቄ :status ሆኗል።',
            'sms' => 'Temari.et፦ የተማሪ መታወቂያ ይገባኛልዎ (:public_id) :status ሆኗል።',
        ],
        'account_link_requested' => [
            'title' => 'የሚገመገም የተማሪ መታወቂያ ይገባኛል',
            'body' => 'አንድ ሰው :student ነኝ ብሏል። የመለያ ማገናኘት ጥያቄውን ይገምግሙ።',
        ],
        'card_request_decided' => [
            'title' => 'የመታወቂያ ካርድ ሁኔታ',
            'body' => 'የ:name የመታወቂያ ካርድ ጥያቄ አሁን :status ነው።',
        ],
    ],

    'tutoring' => [
        'application_approved' => [
            'title' => 'አሁን የTemari.et አስጠኚ ነዎት',
            'body' => 'የአስጠኚነት ማመልከቻዎ ጸድቋል — መገለጫዎ በማውጫው ላይ ታይቷል። እንኳን ደህና መጡ!',
            'sms' => 'Temari.et፦ የአስጠኚነት ማመልከቻዎ ጸድቋል። መገለጫዎ ታይቷል — ጥያቄዎችን ለማስተዳደር ይግቡ።',
        ],
        'application_declined' => [
            'title' => 'የአስጠኚነት ማመልከቻ ውድቅ ተደርጓል',
            'body' => 'ማመልከቻዎ ውድቅ ተደርጓል፦ :reason። ማመልከቻዎን አሻሽለው እንደገና ማስገባት ይችላሉ።',
        ],
        'profile_suspended' => [
            'title' => 'የአስጠኚ መገለጫ ታግዷል',
            'body' => 'የአስጠኚ መገለጫዎ ታግዷል፦ :reason። ለዝርዝር የTemari.et ድጋፍን ያግኙ።',
        ],
        'request_received' => [
            'title' => 'አዲስ የማስጠናት ጥያቄ',
            'body' => ':name ሊቀጥሩዎት ይፈልጋሉ። ከጥያቄዎች ሳጥንዎ ገምግመው ይመልሱ።',
            'sms' => 'Temari.et፦ አዲስ የማስጠናት ጥያቄ አለዎት። ለመመለስ ይግቡ።',
        ],
        'request_accepted' => [
            'title' => 'አስጠኚው ጥያቄዎን ተቀብለዋል',
            'body' => ':name የማስጠናት ጥያቄዎን ተቀብለዋል። ትምህርቱ እንዲጀመር የመጀመሪያውን ወር ይክፈሉ።',
            'sms' => 'Temari.et፦ የማስጠናት ጥያቄዎ ተቀባይነት አግኝቷል። ትምህርት ለመጀመር የመጀመሪያውን ወር ይክፈሉ።',
        ],
        'request_declined' => [
            'title' => 'የማስጠናት ጥያቄ ውድቅ ተደርጓል',
            'body' => ':name የማስጠናት ጥያቄዎን አልተቀበሉም። በማውጫው ሌሎች አስጠኚዎችን ይመልከቱ።',
        ],
        'engagement_ended' => [
            'title' => 'የማስጠናት ውል ተጠናቋል',
            'body' => ':name የማስጠናት ውሉን አጠናቀዋል።',
        ],
        'cycle_due' => [
            'title' => 'የማስጠናት ወር ክፍያ ደርሷል',
            'body' => ':label ከ:tutor ጋር — :amount ብር ይከፈላል። ወሩ ሲከፈል ትምህርቶቹ ይጀመራሉ።',
            'sms' => 'Temari.et፦ የ:label ማስጠናት ከ:tutor — :amount ብር ይከፈላል። ትምህርት ለመጀመር በመተግበሪያው ይክፈሉ።',
        ],
        'cycle_funded' => [
            'title' => 'ወሩ ተከፍሏል — ማስተማር ይችላሉ',
            'body' => ':label ተከፍሎ በTemari.et ተይዟል። ክፍለ-ጊዜዎችዎን ያቅዱ።',
            'sms' => 'Temari.et፦ የ:label ማስጠናት ተከፍሏል (በአደራ ተይዟል)። አሁን ክፍለ-ጊዜዎችን ማቀድ ይችላሉ።',
        ],
        'cycle_released' => [
            'title' => 'ገቢዎ ተለቅቋል',
            'body' => ':label ተጠናቋል — :amount ብር ወደ ቦርሳዎ ተጨምሯል።',
            'sms' => 'Temari.et፦ ለ:label የ:amount ብር ወደ አስጠኚ ቦርሳዎ ተለቅቋል።',
        ],
        'session_scheduled' => [
            'title' => 'የማስጠናት ክፍለ-ጊዜ ታቅዷል',
            'body' => 'አስጠኚዎ ለ:when ክፍለ-ጊዜ አቅደዋል።',
        ],
        'session_logged' => [
            'title' => 'የማስጠናት ክፍለ-ጊዜውን ያረጋግጡ',
            'body' => 'አስጠኚዎ የ:hours ሰዓት ክፍለ-ጊዜ መዝግበዋል። በ72 ሰዓት ውስጥ ያረጋግጡ (ወይም ቅሬታ ያቅርቡ)።',
        ],
        'session_disputed' => [
            'title' => 'በክፍለ-ጊዜ ላይ ቅሬታ ቀርቧል',
            'body' => 'አንድ ቤተሰብ በመዘገቡት ክፍለ-ጊዜ ላይ ቅሬታ አቅርቧል። Temari.et ይገመግመዋል።',
        ],
        'payout_paid' => [
            'title' => 'ክፍያ ተልኳል',
            'body' => 'የ:amount ብር ክፍያዎ ወደ መለያዎ ተልኳል።',
            'sms' => 'Temari.et፦ የአስጠኚ ክፍያዎ :amount ብር ወደ መለያዎ ተልኳል።',
        ],
        'review_received' => [
            'title' => 'አዲስ ግምገማ',
            'body' => 'አንድ ቤተሰብ :rating/5 ሰጥተውዎታል። የይፋ መገለጫዎን ይመልከቱ።',
        ],
    ],

    'system' => [
        'timetable_generated' => [
            'title' => 'የሰሌዳ ዝግጅት ተጠናቋል',
            'body' => 'የ:term የሰሌዳ ረቂቅ ለግምገማና ማስተካከያ ዝግጁ ነው።',
        ],
        'term_results_computed' => [
            'title' => 'የተርም ውጤቶች ተሰልተዋል',
            'body' => 'የ:term ውጤቶች ተቆልፈዋል — ሪፖርት ካርዶች ዝግጁ ናቸው።',
        ],
        'student_import_completed' => [
            'title' => 'የተማሪ ማስመጣት ተጠናቋል',
            'body' => ':file — :imported ተማሪዎች ገብተዋል፣ :failed አልተሳኩም። ዝርዝሩን ለማየት ሪፖርቱን ይክፈቱ።',
        ],
    ],
    // ── Temari AI ──
    'ai' => [
        'weekly_briefing' => [
            'title' => 'ሳምንታዊ የትምህርት ቤት AI ማጠቃለያ',
            'body' => ':summary',
        ],
        'parent_digest' => [
            'title' => 'የዚህ ሳምንት የ :student ሁኔታ',
            'body' => ':summary',
        ],
    ],
];
