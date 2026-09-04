import type { MarketingDict } from "./types"

export const en: MarketingDict = {
  locale: "en",
  announcement: {
    text: "New schools run their first full semester free",
    cta: "See pricing",
  },
  nav: {
    features: "Features",
    examPrep: "Exam prep",
    tutors: "Tutors",
    pricing: "Pricing",
    about: "About",
    signIn: "Sign in",
    getStarted: "Get started",
    openApp: "Open app",
    menu: "Menu",
    language: "Language",
  },
  footer: {
    tagline:
      "The school platform built for Ethiopia. Attendance, fees, report cards and exam prep in one place.",
    product: "Product",
    audiences: "Who it's for",
    company: "Company",
    columns: {
      product: [
        { label: "Features", path: "/features" },
        { label: "Exam prep", path: "/exam-prep" },
        { label: "Pricing", path: "/pricing" },
        { label: "FAQ", path: "/faq" },
      ],
      audiences: [
        { label: "Schools", path: "/for/schools" },
        { label: "Teachers", path: "/for/teachers" },
        { label: "Parents", path: "/for/parents" },
        { label: "Students", path: "/for/students" },
        { label: "Tutors", path: "/tutors" },
      ],
      company: [
        { label: "About", path: "/about" },
        { label: "Contact", path: "/contact" },
        { label: "Privacy", path: "/privacy" },
        { label: "Terms", path: "/terms" },
      ],
    },
    copyright: "Temari.et",
    madeIn: "Made in Addis Ababa",
  },
  common: {
    learnMore: "Learn more",
    getStarted: "Get started",
    talkToUs: "Talk to us",
    seePricing: "See pricing",
    allFeatures: "All features",
    relatedFeatures: "Related features",
    startPracticing: "Start practicing",
  },
  ctaBand: {
    headline: "Bring your school onto Temari",
    sub: "Set up your branches, register students and start taking attendance the same week — your first semester is on us.",
    primary: "Get started",
    secondary: "Talk to us",
  },
  home: {
    meta: {
      title: "Temari.et: School management software for Ethiopia",
      description:
        "Attendance, school fees, report cards, timetables, smart ID cards and SMS to parents in Amharic, Afaan Oromo and English. Plus Grade 6, 8 and 12 exam prep and tutors. Built for Ethiopian schools.",
    },
    audiences: {
      headline: "What brings you here?",
      sub: "Temari is one platform with a home for everyone around a school. Pick yours.",
      items: [
        {
          title: "I run a school",
          body: "Enrollment, fees, attendance, academics and staff across every branch, live on one screen.",
          href: "/for/schools",
        },
        {
          title: "I teach",
          body: "Attendance in a minute, marks without a calculator, lesson plans and homework from your phone.",
          href: "/for/teachers",
        },
        {
          title: "I'm a parent",
          body: "Your child's attendance, results and fees on your phone — and the important ones by SMS.",
          href: "/for/parents",
        },
        {
          title: "I'm a student",
          body: "Your timetable, homework, quizzes and results in one app, on any phone.",
          href: "/for/students",
        },
        {
          title: "I'm preparing for national exams",
          body: "Grade 6, 8 and 12 mock papers with explanations — free to start, no school needed.",
          href: "/exam-prep",
        },
        {
          title: "I'm looking for a tutor",
          body: "Find a verified tutor near you, agree terms, and pay safely month by month.",
          href: "/tutors",
        },
      ],
    },
    hero: {
      badge: "ተማሪ · ለተማሪ",
      headline: "Run your whole school",
      headline2: "from one place",
      sub: "Attendance, fees, report cards and SMS to parents. Built for Ethiopian schools, on any phone, in three languages.",
      primary: "Get started",
      secondary: "Talk to us",
      note: "≈ 55 santim per child, per day · First semester free for new schools",
    },
    banks: {
      title: "Verify fee payments from the channels families already use",
    },
    schools: {
      title: "Schools running on Temari",
    },
    stats: [
      { value: "3", label: "Languages, everywhere" },
      { value: "6·8·12", label: "National exam grades covered" },
      { value: "13", label: "Months, one calendar" },
      { value: "24/7", label: "Open from anywhere" },
    ],
    testimonials: {
      headline: "What schools say",
      sub: "From the office to the classroom to the kitchen table.",
      items: [
        {
          quote:
            "Before Temari, closing a semester took our registrar two weeks of cross-checking paper marklists. Now the report cards are ready the day the term closes.",
          name: "Rahel Tesfaye",
          role: "Principal, Addis Ababa",
        },
        {
          quote:
            "I get an SMS the same morning my son misses class. That alone was worth it for our family.",
          name: "Mohammed Yusuf",
          role: "Parent of two",
        },
        {
          quote:
            "Attendance takes me under a minute and the quiz marks land straight in my marklist. I actually teach more.",
          name: "Selamawit Bekele",
          role: "Maths teacher, Grade 9",
        },
      ],
    },
    features: {
      headline: "Everything a school runs, in one system",
      sub: "One record per student, from registration to transcript. No double entry, no parallel paper books.",
    },
    tour: {
      headline: "See it in action",
      sub: "Real screens from Temari — the same product on a phone in the classroom and a computer in the office.",
      items: [
        {
          title: "The whole school at a glance",
          body: "Enrollment, attendance, collections and everything waiting for a decision — live, across every branch.",
        },
        {
          title: "The student register, always current",
          body: "Every student with status, section and ID — searchable by name in three languages, imported from Excel in minutes.",
        },
        {
          title: "A clash-free timetable",
          body: "Generated automatically around teacher availability, rooms and double periods — then published to everyone.",
        },
        {
          title: "Every invoice, every birr",
          body: "Automatic monthly billing on the Ethiopian calendar, verified payments and books that always balance.",
        },
        {
          title: "Lesson plans in the MoE format",
          body: "Annual roadmaps and weekly plans, reviewed and approved in the same place teachers work.",
        },
      ],
    },
    parents: {
      headline: "Parents stay informed without a smartphone",
      sub: "SMS is the primary channel. Absences, fee reminders and results reach the family phone in the language they chose.",
      points: [
        {
          title: "Absence alerts the same morning",
          body: "When a child is marked absent, the guardian's phone gets a message before lunch, not at the end of the semester.",
        },
        {
          title: "Fee reminders that name the amount",
          body: "Families see exactly what is due, when, and where to pay. Receipts arrive with a QR code anyone can verify.",
        },
        {
          title: "Results without the queue",
          body: "Report cards and attendance summaries are in the family portal, and the key numbers go out by SMS.",
        },
      ],
    },
    ethiopia: {
      headline: "Built for Ethiopia, not adapted to it",
      sub: "The details that break foreign software are the foundation here.",
      items: [
        {
          title: "Ethiopian calendar & clock",
          body: "Academic years, semesters and payroll run on the Ethiopian calendar and dawn-count clock. Prefer Gregorian dates or standard time? One setting switches the display — official documents print both calendars.",
        },
        {
          title: "Three languages",
          body: "The interface, SMS and documents work in Amharic, Afaan Oromo and English. Each user picks their own.",
        },
        {
          title: "Works on slow internet",
          body: "Pages are light and fast on a budget Android phone. Attendance and mark entry keep working when the network drops.",
        },
        {
          title: "Patronymic names",
          body: "First name, father's name, grandfather's name. The data model matches how Ethiopians are actually named.",
        },
      ],
    },
    examPrep: {
      headline: "National exam prep for Grade 6, 8 and 12",
      sub: "Timed mock exams built from the national curriculum, with explanations for every answer. Open to any student, whether or not their school uses Temari.",
      points: [
        "Practice by subject, chapter or full timed papers",
        "Instant scoring with worked explanations",
        "An AI tutor that answers in your language",
      ],
      cta: "Start practicing",
    },
    trust: {
      headline: "Records a school can stand behind",
      sub: "Receipts, report cards and payslips that prove themselves, and history that never quietly changes.",
      items: [
        {
          title: "Documents that prove themselves",
          body: "Receipts, transcripts and transfer letters carry a QR code anyone can scan to confirm they are genuine.",
        },
        {
          title: "History that stays put",
          body: "When a semester closes, results and payment records freeze. A report card printed today matches the one printed next year.",
        },
        {
          title: "Approvals built in",
          body: "Expense approvals, marklist countersigning and message review follow the way a school actually delegates responsibility.",
        },
      ],
    },
    pricing: {
      headline: "55 santim a day",
      sub: "That is what the whole platform costs a family — records, results, SMS and exam prep for their child.",
      price: "55 santim",
      unit: "per child, per day",
      note: "Parents pay once a year at registration. Schools pay nothing for the core — and a new school's first full semester is completely free.",
      cta: "See pricing",
    },
  },
  featuresIndex: {
    meta: {
      title: "Features: everything Temari does for your school",
      description:
        "Student records, attendance with SMS alerts, smart ID cards, fee collection and verification, continuous assessment, report cards, exams, courses, timetables, HR, payroll and inventory.",
    },
    hero: {
      headline: "One platform, the whole school day",
      sub: "Every module reads and writes the same student record, so the office, the classroom and the family portal always agree.",
    },
  },
  features: {
    "student-management": {
      name: "Student records",
      tagline: "One clean record per student, from registration to transfer.",
      meta: {
        title: "Student information system for Ethiopian schools",
        description:
          "Register students step by step, import whole registers from Excel, manage guardians, documents and transfers. Patronymic names and Ethiopian IDs built in.",
      },
      hero: {
        headline: "Every student, one record, zero re-typing",
        sub: "Registration, guardians, documents, health notes and enrollment history live together. The record follows the student across branches and even between schools.",
      },
      capabilities: [
        {
          title: "Guided registration",
          body: "A step-by-step form captures the student, guardians, documents and first invoice in one pass. Duplicates are caught before they are created.",
        },
        {
          title: "Bulk import from Excel",
          body: "Bring an existing register across in minutes. The file is checked row by row and problems are reported per student, not as a failed upload.",
        },
        {
          title: "Guardians with real permissions",
          body: "Each guardian link controls what that person can see and pay for. A student can have several guardians with different roles.",
        },
        {
          title: "Transfers with a paper trail",
          body: "Transfers between Temari schools carry the file forward: documents, history and clearance, with a QR-verified transfer letter.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Printable student ID with QR sign-in for phone-less students",
          "Era-scoped archives: a former school keeps its own records, never your new ones",
          "Section assignment and balancing tools for large intakes",
          "Promotion decisions and year-end rollover in one workflow",
        ],
      },
      related: ["attendance", "fees", "grading"],
    },
    attendance: {
      name: "Attendance",
      tagline: "Daily and per-period marking, card readers and same-day SMS.",
      meta: {
        title: "School attendance with SMS alerts and card readers",
        description:
          "Mark attendance from any phone, or let tap-in card readers at the gate do it. Guardians get same-day SMS alerts. Reports spot chronic absence early.",
      },
      hero: {
        headline: "Know who is in school, and tell the family",
        sub: "Homeroom teachers mark from a phone in under a minute, or a card reader at the gate does it automatically. Absences reach guardians by SMS the same morning.",
      },
      capabilities: [
        {
          title: "One-minute marking",
          body: "The homeroom list defaults everyone to present. The teacher only touches the exceptions, from any phone, even offline.",
        },
        {
          title: "Tap-in ID cards",
          body: "Students tap their ID at the gate and the record writes itself. A teacher's manual mark always wins over the machine.",
        },
        {
          title: "Same-day guardian SMS",
          body: "An absence sends one clear message to the guardian, in their language, and never sent twice for the same event.",
        },
        {
          title: "Reports that catch patterns",
          body: "Chronic absence and perfect attendance surface automatically, per section, per grade or per branch.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Employee attendance with leave and holiday overlays",
          "Absence excuses submitted by parents from the family portal",
          "Period-level attendance for secondary schools",
          "Works through network drops and syncs when back online",
        ],
      },
      related: ["id-cards", "communication", "timetable"],
    },
    "id-cards": {
      name: "Smart ID cards",
      tagline: "Tap-in ID cards that take attendance for students and staff.",
      meta: {
        title: "Smart student ID cards with automatic attendance",
        description:
          "Printed smart ID cards for students and employees. A tap at the gate records attendance automatically, guardians get same-day SMS, and the card's QR signs phone-less students into the app.",
      },
      hero: {
        headline: "One card: identity, attendance, sign-in",
        sub: "Students and employees tap their card at the gate and attendance writes itself. The same card carries a QR code that signs a phone-less student into their own account.",
      },
      capabilities: [
        {
          title: "Automatic gate attendance",
          body: "A reader at the gate records each tap the moment it happens. No queue at the office, no morning roll-call paperwork.",
        },
        {
          title: "Staff and students, same system",
          body: "Employee cards work on the same readers, so staff attendance flows into HR without a separate machine or register.",
        },
        {
          title: "Guardians told the same morning",
          body: "A student who has not tapped in triggers the same-day SMS alert to the family, in their language.",
        },
        {
          title: "A teacher's mark always wins",
          body: "The machine is an assistant, not the authority. A homeroom teacher's manual mark overrides the reader every time.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "A QR on the card signs phone-less students into their portal with just a PIN",
          "Lost cards are deactivated instantly and reissued without losing history",
          "Readers work per gate and per branch, sized to your campus",
          "The card ties back to the same single student record as everything else",
        ],
      },
      related: ["attendance", "student-management", "communication"],
    },
    fees: {
      name: "Fees & finance",
      tagline:
        "Invoices, Telebirr and bank verification, receipts and school books.",
      meta: {
        title: "School fee collection and finance for Ethiopia",
        description:
          "Invoice every student automatically, verify Telebirr and bank payments, print QR receipts, manage scholarships and run the school's books.",
      },
      hero: {
        headline: "Every birr accounted for",
        sub: "Families pay by Telebirr, bank or cash. Temari invoices, verifies, receipts and reports — so the office always balances.",
      },
      capabilities: [
        {
          title: "Automatic invoicing",
          body: "Fee structures generate invoices per student on the Ethiopian calendar, with sibling and scholarship discounts applied by policy, not by memory.",
        },
        {
          title: "Payment verification",
          body: "Families submit their Telebirr or bank reference from the portal. Finance verifies against the school's own accounts and the receipt is issued.",
        },
        {
          title: "Receipts with proof",
          body: "Every receipt carries a QR code that anyone can scan to confirm it is genuine, without revealing other family data.",
        },
        {
          title: "The school's books",
          body: "Expenses with four-eyes approval, budgets against actuals, cashbook-style reports and payroll, all in the same system as the income.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Standing concessions for scholarships, staff children and siblings, with an approval queue",
          "Fee reminders by SMS that state the exact amount due",
          "Registration-fee gating so enrollment and payment stay consistent",
          "Collection accounts per branch, with history that never rewrites itself",
        ],
      },
      related: ["communication", "student-management", "hr-payroll"],
    },
    grading: {
      name: "Assessment & report cards",
      tagline:
        "Continuous assessment, marklists, ranked report cards, transcripts.",
      meta: {
        title: "Continuous assessment and report cards for Ethiopian schools",
        description:
          "Plan continuous assessment, enter marks fast, freeze semester results with ranks, and print report cards and Ethiopian year-grid transcripts with QR verification.",
      },
      hero: {
        headline: "From marklist to report card without a calculator",
        sub: "Teachers enter marks once. Averages, ranks, letter grades and report cards follow the school's own grading policy automatically.",
      },
      capabilities: [
        {
          title: "Continuous assessment plans",
          body: "Each subject plans its assessments and weights per semester. The marklist is generated from the plan, so nothing is forgotten.",
        },
        {
          title: "Fast, safe mark entry",
          body: "A keyboard-first grid built for a full class period of marks. Locked marklists and closed terms cannot be edited by accident.",
        },
        {
          title: "Frozen semester results",
          body: "When a term closes, averages and section ranks freeze. Report cards and promotion decisions read the frozen record, so history never shifts.",
        },
        {
          title: "Transcripts schools can defend",
          body: "The Ethiopian year-grid transcript prints from frozen results with a QR code that verifies it came from the school.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "School-defined grading scales and letter policies",
          "Roster reports and mark distribution analysis per section and subject",
          "Promotion suggestions computed from frozen results",
          "Bulk printing for report cards and transcripts",
        ],
      },
      related: ["lms", "student-management", "communication"],
    },
    lms: {
      name: "Exams & assignments",
      tagline:
        "Question banks, secure online exams, homework and course materials.",
      meta: {
        title: "Online exams, assignments and course materials",
        description:
          "Build question banks by subject and chapter, run secure timed exams, collect assignments with clear marking criteria, and share course materials. Marks sync to the report card.",
      },
      hero: {
        headline: "Classwork that grades itself into the report card",
        sub: "Quizzes, exams and assignments run online, score automatically where they can, and push results straight into continuous assessment. No double entry.",
      },
      capabilities: [
        {
          title: "Question banks per subject",
          body: "Teachers build reusable banks by subject, grade and chapter, with rich text, images, math notation and reading passages.",
        },
        {
          title: "Exams that hold up",
          body: "Each student gets their own shuffled paper with a strict time limit. Suspicious behavior is flagged for review, never auto-punished.",
        },
        {
          title: "Assignments marked fairly",
          body: "Homework, projects and papers collect submissions online with clear marking criteria and a discussion thread per student.",
        },
        {
          title: "One gradebook, no re-typing",
          body: "A graded quiz or assignment links to an assessment slot and its scores land in the marklist, rescaled to the plan's weight.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Course materials shared once and targeted to the right sections",
          "Structured courses with modules, lessons and progress tracking",
          "Multi-section exams from one paper, with per-section stats",
          "Printable A4 exam papers generated from the same questions",
        ],
      },
      related: ["courses", "grading", "timetable"],
    },
    courses: {
      name: "Courses & materials",
      tagline: "Structured online courses, lessons and shared class materials.",
      meta: {
        title: "Online courses and learning materials for schools",
        description:
          "Build structured courses with modules and lessons, track each student's progress, and share class materials once to exactly the right sections. Built for slow internet.",
      },
      hero: {
        headline: "Lessons that live beyond the classroom",
        sub: "Teachers build courses out of modules, lessons and materials. Students follow them from any phone, and progress is visible to teacher and family alike.",
      },
      capabilities: [
        {
          title: "Courses with real structure",
          body: "Modules, ordered lessons, readings and video — assembled once and reused across sections and years.",
        },
        {
          title: "Materials shared once",
          body: "A worksheet or reading is uploaded one time and targeted to the right grades and sections. No re-uploading per class.",
        },
        {
          title: "Progress you can see",
          body: "Each student's position in the course is tracked, so a teacher knows who is keeping up before the exam does.",
        },
        {
          title: "Connected to the gradebook",
          body: "Quizzes and assignments inside a course link to assessment slots, so course work counts where it should.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Lessons unlock in order or all at once — the teacher decides",
          "Light pages and compressed media that load on 3G",
          "Families see the same approved content as the student",
          "Works alongside exam prep for Grade 6, 8 and 12",
        ],
      },
      related: ["lms", "grading", "timetable"],
    },
    timetable: {
      name: "Timetable & lesson plans",
      tagline: "Automatic clash-free timetables and weekly lesson plans.",
      meta: {
        title: "School timetable generator and lesson planning",
        description:
          "Generate clash-free timetables automatically, respecting teacher availability, rooms and subject rules. Plan lessons annually and weekly with pacing checks.",
      },
      hero: {
        headline: "A week that schedules itself",
        sub: "Describe your periods, subjects and teachers. Temari builds a clash-free timetable you can adjust by hand and publish each semester.",
      },
      capabilities: [
        {
          title: "Automatic generation",
          body: "Temari respects teacher availability, daily limits, double periods and room needs like labs and gyms. Periods you lock stay exactly where you put them.",
        },
        {
          title: "Versioned publishing",
          body: "Draft, tune and publish per term. Teachers and families always see the published version, never a work in progress.",
        },
        {
          title: "Annual and weekly lesson plans",
          body: "Teachers plan the year's chapters, then each week's lessons against them. Directors review and approve in the same place.",
        },
        {
          title: "Pacing that keeps up",
          body: "If last week's lessons were not covered, the next plan asks why. Coverage tracking shows where each class actually is.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Period schedules per term, so re-timing a day never breaks the timetable",
          "Families see approved plans and this week's topics in the portal",
          "Teacher schedules and free-period views",
          "A guided first-time setup that gets a school to a published timetable quickly",
        ],
      },
      related: ["lms", "attendance", "grading"],
    },
    communication: {
      name: "Messaging & SMS",
      tagline:
        "One notification pipeline: in-app feed, SMS and email, plus chat.",
      meta: {
        title: "School to parent SMS and messaging",
        description:
          "Absence alerts, fee reminders and results by SMS in the family's language. In-app messaging between staff and parents with an approval gate for teachers.",
      },
      hero: {
        headline: "The right message, the right channel, once",
        sub: "Everything a family should know flows through one pipeline: an in-app feed always, SMS and email when it matters, in the language each person chose.",
      },
      capabilities: [
        {
          title: "SMS where it counts",
          body: "Absences, fee reminders, receipts and results reach the guardian's phone. Critical alerts always go through; noise is deduplicated.",
        },
        {
          title: "Messages in their language",
          body: "Each recipient's preference decides the language of every SMS, email and in-app notice. Amharic, Afaan Oromo or English.",
        },
        {
          title: "Direct and group chat",
          body: "Staff rooms, section channels and direct messages, with a family thread per student so parents talk to the school in one place.",
        },
        {
          title: "A communication book with a gate",
          body: "Teacher-to-parent messages can require homeroom or director approval before they send, matching how schools actually work.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Per-category notification preferences per user, with critical events piercing mutes",
          "On-demand fee notices for a class or the whole school",
          "Deep links: every notification opens the exact screen it is about",
          "SMS cost control with a platform-level whitelist of events",
        ],
      },
      related: ["attendance", "fees", "student-management"],
    },
    "hr-payroll": {
      name: "HR & payroll",
      tagline: "Employees, positions, leave, attendance and Ethiopian payroll.",
      meta: {
        title: "School HR and payroll for Ethiopia",
        description:
          "Employee files, job positions, leave per Labour Proclamation 1156/2019, staff attendance, and payroll with Ethiopian income tax and pension computed correctly.",
      },
      hero: {
        headline: "The staff side, done properly",
        sub: "From hiring to payslip: employee files, multiple positions, leave balances on the Ethiopian year, and payroll that computes tax and pension by law.",
      },
      capabilities: [
        {
          title: "Real employee files",
          body: "One HR file per person per branch, with documents, positions and history. A teacher who is also a director is one person, two jobs.",
        },
        {
          title: "Leave by the law",
          body: "Leave types follow Labour Proclamation 1156/2019 defaults, counted in working days on the Ethiopian leave year, with an approval workflow.",
        },
        {
          title: "Payroll that freezes",
          body: "Income tax per Proclamation 1395/2025 and 7/11 pension are computed automatically. An approved run freezes its breakdown forever.",
        },
        {
          title: "Payslips and statements",
          body: "Staff see their own payslips, leave balances and attendance in self-service. Finance gets the reports.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Role-mapped positions: hiring a teacher grants teacher access automatically",
          "Employee attendance with holiday and approved-leave overlays",
          "Allowances, deductions and loans reflected in the frozen breakdown",
          "QR-verified payslip documents",
        ],
      },
      related: ["fees", "attendance", "communication"],
    },
    inventory: {
      name: "Inventory & assets",
      tagline: "Store ledger, requisitions, asset custody and textbook lending.",
      meta: {
        title: "School inventory, asset and textbook management",
        description:
          "A digital bin card for every store item, staff requisitions with approvals, purchase orders, stock takes, an asset register with custody, and textbook lending per student.",
      },
      hero: {
        headline: "Every item counted, every holder known",
        sub: "From chalk to laptops to textbooks: a store ledger that is always current, approvals that match your hierarchy, and a clear answer to \"who has it?\"",
      },
      capabilities: [
        {
          title: "A digital bin card",
          body: "Every stock movement is a signed entry with a running balance — the store book your auditor already understands, kept automatically.",
        },
        {
          title: "Requisitions with approvals",
          body: "Staff request, an approver countersigns — never their own request — and the storekeeper issues. Partial issues are fine.",
        },
        {
          title: "An asset register with custody",
          body: "Laptops, projectors and lab kits carry a tag and a custody chain. Clearance becomes one question: has this person returned everything?",
        },
        {
          title: "Textbook lending by the section",
          body: "Issue a book to a whole section in one action, track returns per student, and see exactly which copies never came back.",
        },
      ],
      deepDive: {
        title: "Also in this module",
        points: [
          "Optional purchase orders for the schools that buy formally",
          "Stock takes that post differences against the live balance",
          "Low-stock alerts when an item crosses its reorder level",
          "Items with history are deactivated, never deleted",
        ],
      },
      related: ["hr-payroll", "fees", "student-management"],
    },
  },
  audiences: {
    schools: {
      name: "Schools",
      meta: {
        title: "Temari for schools and directors",
        description:
          "One platform for the whole school: enrollment, attendance, fees, report cards, timetables, HR and payroll. Multi-branch by design, with the core paid by families.",
      },
      hero: {
        headline: "The whole school, visible from one screen",
        sub: "Directors and principals see enrollment, attendance, collections and academics live, across every branch, without calling anyone.",
      },
      points: [
        {
          title: "Your first semester is free",
          body: "Run the whole platform with your whole school for a full semester before paying anything. Decide with real report cards in hand.",
        },
        {
          title: "The core costs the school nothing",
          body: "Families pay just 55 santim per child per day for the platform. The school pays only for optional add-ons it chooses.",
        },
        {
          title: "Onboarding done with you",
          body: "We import your student register from Excel, set up fees and sections with you, and train your staff in Amharic, Afaan Oromo or English.",
        },
        {
          title: "Multi-branch from day one",
          body: "Each branch runs its own operations while the school leadership works across all of them from a single workspace.",
        },
        {
          title: "Money with controls",
          body: "Four-eyes expense approval, director finance gating and frozen payment history. The platform enforces the separation your auditor expects.",
        },
        {
          title: "Setup measured in days",
          body: "Import your student register from Excel, define fees and sections, and run attendance the same week.",
        },
      ],
      featuresTitle: "What your team gets",
      featureLinks: [
        "student-management",
        "fees",
        "attendance",
        "id-cards",
        "grading",
        "timetable",
        "hr-payroll",
        "inventory",
      ],
    },
    teachers: {
      name: "Teachers",
      meta: {
        title: "Temari for teachers",
        description:
          "Attendance in a minute, mark entry without a calculator, lesson plans, online quizzes and homework, and a timetable in your pocket. On any phone.",
      },
      hero: {
        headline: "Less paperwork, more teaching",
        sub: "Attendance, marks, lesson plans and homework from the phone already in your pocket. The clerical part of teaching, mostly gone.",
      },
      points: [
        {
          title: "Attendance in under a minute",
          body: "Everyone defaults to present. Touch the exceptions and you are done, even when the network is not cooperating.",
        },
        {
          title: "Marks without arithmetic",
          body: "Enter raw scores; weights, averages, ranks and letter grades follow the school's policy automatically.",
        },
        {
          title: "Homework that grades itself",
          body: "Quizzes score automatically and land in your marklist. Assignments collect submissions in one place instead of your inbox.",
        },
        {
          title: "Your day at a glance",
          body: "Today's periods, your sections and pending work on one screen when you open the app.",
        },
      ],
      featuresTitle: "Built into your day",
      featureLinks: ["attendance", "grading", "lms", "timetable"],
    },
    parents: {
      name: "Parents",
      meta: {
        title: "Temari for parents and guardians",
        description:
          "Follow your child's attendance, results and fees from your phone, or by SMS in Amharic, Afaan Oromo or English. Pay the school directly and get a verifiable receipt.",
      },
      hero: {
        headline: "Know how your child is doing, today",
        sub: "Attendance, results and fees on your phone, and the important ones by SMS. One account covers all your children, even at different schools.",
      },
      points: [
        {
          title: "Same-day absence alerts",
          body: "If your child is marked absent, you hear about it that morning by SMS, in your language.",
        },
        {
          title: "Fees without surprises",
          body: "See exactly what is due and when. Pay the school directly by Telebirr or bank, submit the reference, and get a QR-verified receipt.",
        },
        {
          title: "Results as they happen",
          body: "Report cards, attendance summaries and approved lesson plans are in your portal. No queueing at the school gate.",
        },
        {
          title: "All your children, one account",
          body: "Every child linked to you appears in one app, with the school controlling exactly what each guardian can see.",
        },
      ],
      featuresTitle: "What you can see",
      featureLinks: ["attendance", "fees", "grading", "communication"],
    },
    students: {
      name: "Students",
      meta: {
        title: "Temari for students",
        description:
          "See your timetable, homework, quiz results and report card in one app. Practice for Grade 6, 8 and 12 national exams with instant feedback.",
      },
      hero: {
        headline: "Your classes, homework and results in one app",
        sub: "Check today's periods, submit assignments, take quizzes and see your marks the moment they publish. Works on any phone, no smartphone required to have an account.",
      },
      points: [
        {
          title: "Today, at a glance",
          body: "Your timetable, due homework and new results on one home screen designed like the apps you already use.",
        },
        {
          title: "Homework in one place",
          body: "Every assignment, its deadline and your submission status. Upload from your phone and track feedback.",
        },
        {
          title: "Quizzes with instant results",
          body: "Take class quizzes and exams online. Auto-marked questions score instantly; everything lands in your record.",
        },
        {
          title: "Exam practice built in",
          body: "Mock exams for Grade 6, 8 and 12 with explanations for every answer, free to start with any account.",
        },
      ],
      featuresTitle: "Made for your school day",
      featureLinks: ["lms", "timetable", "grading", "attendance"],
    },
  },
  examPrep: {
    meta: {
      title: "Grade 6, 8 and 12 national exam prep for Ethiopia",
      description:
        "Practice for Ethiopian national exams with timed mock papers, instant scoring, worked explanations and an AI tutor. Free to start, works on any phone.",
    },
    hero: {
      badge: "Grade 6 · 8 · 12",
      headline: "Walk into the national exam ready",
      sub: "Timed mock papers built from the national curriculum, scored instantly, with explanations for every answer. On the phone you already have.",
      primary: "Start practicing",
    },
    grades: [
      {
        grade: "Grade 6",
        title: "Primary completion",
        body: "Build the fundamentals across every subject before the first national milestone.",
      },
      {
        grade: "Grade 8",
        title: "Middle school completion",
        body: "Subject-by-subject practice with the pacing and difficulty of the real paper.",
      },
      {
        grade: "Grade 12",
        title: "EUEE university entrance",
        body: "Stream-aware practice for natural and social sciences, aimed at the score you need.",
      },
    ],
    how: {
      headline: "How it works",
      steps: [
        {
          title: "Create a free account",
          body: "Sign up with a phone number. You do not need to attend a Temari school.",
        },
        {
          title: "Pick your grade and subjects",
          body: "Practice by chapter to learn, or take full timed papers to test yourself under exam conditions.",
        },
        {
          title: "Learn from every answer",
          body: "Each question comes back with the correct answer and a worked explanation, so a wrong answer still teaches.",
        },
      ],
    },
    ai: {
      headline: "An AI tutor that speaks your language",
      sub: "The upgrade adds a tutor that explains, re-explains and drills you where you are weak.",
      points: [
        {
          title: "Ask anything, in your words",
          body: "Stuck on a solution? Ask in Amharic, Afaan Oromo or English and get an explanation that fits the question.",
        },
        {
          title: "A path that adapts",
          body: "Your practice history shapes what you see next, so time goes to the chapters that need it.",
        },
        {
          title: "Works on a weak connection",
          body: "Short, focused answers that load even on slow internet, not video lectures that never finish loading.",
        },
      ],
    },
    pricingNote: {
      title: "Free to practice, 199 ETB per month for the AI upgrade",
      body: "Mock papers and instant scoring are free with an account. The AI tutor and adaptive learning path are a monthly upgrade, cancel anytime.",
    },
  },
  pricing: {
    meta: {
      title: "Pricing: 200 ETB per student per year",
      description:
        "The core platform costs 200 ETB per student per year, paid by the parent. Schools pay nothing for the core. Optional School Plan and AI upgrade priced separately.",
    },
    hero: {
      headline: "Simple pricing, stated up front",
      sub: "The core platform is paid by families, not the school. Add-ons are optional, with flat prices you can see before you commit.",
    },
    freeSemester: {
      badge: "Try before you commit",
      title: "Your first semester is free",
      body: "Bring your whole school on and run everything — attendance, fees, report cards, SMS — for one full semester before you pay anything. Commit only when your staff room is convinced.",
      cta: "Start free",
    },
    plans: [
      {
        name: "Core platform",
        price: "55 santim",
        unit: "per student, per day",
        perDay: "Billed as 200 ETB per student / year",
        payer: "Paid by the parent at registration",
        description:
          "The full school system. The school carries no software cost.",
        features: [
          "Student records, attendance and SMS alerts",
          "Fees, invoicing, receipts and finance books",
          "Continuous assessment, report cards and transcripts",
          "Timetables, lesson plans, HR and payroll",
          "Family and student portals in three languages",
        ],
        cta: "Get started",
        href: "/signup",
        highlighted: true,
      },
      {
        name: "School Plan",
        price: "33 santim",
        unit: "per student, per day",
        perDay: "Billed as 10 ETB per student / month",
        payer: "Optional, paid by the school",
        description:
          "Automation on top of the core. Core features are never paywalled.",
        features: [
          "Automated payment verification (check.et)",
          "Electronic revenue receipts when the ministry service is live",
          "School AI for leadership and teachers",
        ],
        cta: "Talk to us",
        href: "/contact",
      },
      {
        name: "AI Exam Prep",
        price: "6.5 birr",
        unit: "per day",
        perDay: "Billed as 199 ETB / month",
        payer: "Optional, paid by the family",
        description: "For Grade 6, 8 and 12 students. Practice stays free.",
        features: [
          "AI tutor in Amharic, Afaan Oromo and English",
          "Adaptive learning path from your practice history",
          "Unlimited mock papers with explanations",
        ],
        cta: "Start practicing",
        href: "/exam-prep",
      },
    ],
    faqTitle: "Pricing questions",
    faq: [
      {
        q: "Can we try Temari before paying anything?",
        a: "Yes. A new school's first full semester is free — the whole platform, the whole school. You commit only after you have closed a semester on Temari and seen the report cards.",
      },
      {
        q: "Does the school pay anything for the core platform?",
        a: "No. The core platform costs about 55 santim per student per day — billed as 200 ETB per year, paid by the parent at registration. The school only pays if it chooses the optional School Plan or extra hardware.",
      },
      {
        q: "What about smart ID cards and gate readers?",
        a: "They are optional hardware. Talk to us and we will size cards and readers to your campus as part of onboarding.",
      },
      {
        q: "What happens if a family cannot pay the 200 ETB?",
        a: "Talk to us. We work with schools on cases where the platform fee is a barrier; a student's schooling should never depend on it.",
      },
      {
        q: "Is the School Plan required?",
        a: "No. It automates payment verification and adds School AI, but every core feature works without it and none are ever moved behind it.",
      },
    ],
  },
  about: {
    meta: {
      title: "About Temari.et",
      description:
        "Temari.et is built in Addis Ababa: one platform for Ethiopian schools, families and students, in three languages.",
    },
    hero: {
      headline: "Software that takes Ethiopian schools seriously",
      sub: "Temari means student. We build the platform Ethiopian schools deserve: correct on the calendar, priced in plain numbers, usable on the phones people actually have.",
    },
    story: [
      "Most school software sold in Ethiopia was designed somewhere else. The calendar is wrong, the names are wrong, the assumption of fast internet and office computers is wrong, and the school pays for all of it.",
      "Temari started from the opposite end: the Ethiopian school as it actually runs. Two semesters starting in Meskerem, continuous assessment feeding ranked report cards, fees paid by Telebirr and bank transfer, families reached by SMS because that is what reaches them.",
      "We build Temari from Addis Ababa as one platform, because a school is one organism: the office, the classroom and the family should read from the same record.",
    ],
    values: [
      {
        title: "Plain numbers, no surprises",
        body: "Our revenue is simple subscriptions with prices stated up front. Schools and families always know exactly what Temari costs.",
      },
      {
        title: "Families are not an afterthought",
        body: "A platform only parents with smartphones can use is not a school platform. SMS, three languages and phone-less student accounts are core design.",
      },
      {
        title: "Correct before clever",
        body: "Frozen results, audited money trails and careful engineering come before any new feature. Schools run on trust.",
      },
    ],
    factsTitle: "At a glance",
    facts: [
      { label: "Based in", value: "Addis Ababa, Ethiopia" },
      { label: "Platform languages", value: "Amharic, Afaan Oromo, English" },
      { label: "Product", value: "temari.et" },
    ],
  },
  contact: {
    meta: {
      title: "Contact Temari.et",
      description:
        "Talk to the Temari team about bringing your school onto the platform, partnerships or support. Based in Addis Ababa.",
    },
    hero: {
      headline: "Talk to us",
      sub: "Whether you run one school or ten branches, we will walk you through the platform and what onboarding looks like.",
    },
    channels: [
      {
        title: "Phone",
        body: "Call us any working day, in your language.",
        value: "0988 155 377",
        href: "tel:+251988155377",
      },
      {
        title: "Phone",
        body: "A second line, if the first is busy.",
        value: "0929 194 872",
        href: "tel:+251929194872",
      },
      {
        title: "Email",
        body: "For demos, onboarding and anything else.",
        value: "info@temari.et",
        href: "mailto:info@temari.et",
      },
      {
        title: "Office",
        body: "The Temari team",
        value: "Addis Ababa, Ethiopia",
        href: "",
      },
    ],
    schools: {
      title: "Bringing a school on board?",
      body: "Tell us your enrollment size and how you handle fees today. A typical school imports its register from Excel and runs attendance within the first week.",
      cta: "Get started",
    },
  },
  faq: {
    meta: {
      title: "Frequently asked questions",
      description:
        "Answers about Temari.et: pricing, SMS and languages, offline use, data privacy, payments and the Grade 6, 8 and 12 exam prep.",
    },
    hero: {
      headline: "Frequently asked questions",
      sub: "The short answers. For anything else, talk to us.",
    },
    groups: [
      {
        title: "The platform",
        items: [
          {
            q: "What is Temari.et?",
            a: "One platform for Ethiopian schools: student records, attendance with smart ID cards, fees, continuous assessment, report cards, exams, courses, timetables, HR, payroll and inventory — plus portals for parents and students, national exam prep and a tutor marketplace.",
          },
          {
            q: "Does it work on the Ethiopian calendar and time?",
            a: "Yes, natively. Academic years, semesters, leave years and recurring fees run on the Ethiopian calendar, and times can show in the Ethiopian dawn-count clock. Schools that prefer Gregorian dates or standard time switch the display in settings — official documents print both calendars.",
          },
          {
            q: "Which languages does it support?",
            a: "Amharic, Afaan Oromo and English. Every user picks their own language, and it applies to the interface and to the SMS and email the school sends them.",
          },
          {
            q: "Does it work with poor internet?",
            a: "It is built for slow internet on budget Android phones. Key classroom work like attendance and mark entry keeps working through network drops and syncs when back online.",
          },
          {
            q: "Can a school with multiple branches use it?",
            a: "Yes. Branches are first-class: each runs its own operations while school leadership works across all branches from one workspace.",
          },
        ],
      },
      {
        title: "Money",
        items: [
          {
            q: "Who pays for Temari?",
            a: "Parents pay about 55 santim per student per day — billed as 200 ETB per year — for the core platform. The school pays nothing for the core; the optional School Plan is 10 ETB per student per month. A new school's first full semester is free.",
          },
          {
            q: "Which payment channels are supported?",
            a: "Telebirr, CBE Birr, M-PESA and transfers to the school's bank accounts, alongside cash recorded at the office. Receipts carry a QR code for verification.",
          },
        ],
      },
      {
        title: "Families & students",
        items: [
          {
            q: "Do parents need a smartphone?",
            a: "No. SMS is the primary channel for absences, fee reminders and results, in the parent's language. The portal is there for those who want more detail.",
          },
          {
            q: "Can a student without a phone have an account?",
            a: "Yes. Students can sign in with their Temari student ID and a PIN. Setup and PIN resets route safely through the primary guardian's phone.",
          },
          {
            q: "Is the exam prep only for Temari schools?",
            a: "No. Anyone can create a free account and practice for Grade 6, 8 and 12 national exams, whether or not their school uses Temari.",
          },
        ],
      },
      {
        title: "Your data",
        items: [
          {
            q: "Who can see a student's data?",
            a: "The student's own school and the guardians that school has linked, each with their own permissions. Access follows the school's real structure, branch by branch.",
          },
          {
            q: "What happens to records when a student transfers?",
            a: "The file travels forward with the student. The former school keeps a frozen archive of its own era only; it never sees what the new school adds.",
          },
          {
            q: "How are documents verified?",
            a: "Receipts, transcripts, transfer letters and payslips carry a QR code linking to a public verification page that proves authenticity without exposing marks or amounts.",
          },
        ],
      },
    ],
  },
}
