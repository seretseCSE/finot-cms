import {
  ArrowLeftRight,
  BookOpen,
  BookOpenCheck,
  Boxes,
  CalendarClock,
  Briefcase,
  CalendarDays,
  ClipboardCheck,
  GraduationCap,
  HandCoins,
  Heart,
  LayoutGrid,
  LibraryBig,
  Map,
  MessagesSquare,
  NotebookPen,
  ScanLine,
  School,
  Sparkles,
  Target,
  TrendingUp,
  UserCheck,
  Users,
  Wallet,
  type LucideIcon,
} from "lucide-react"

/**
 * The docs catalog: WHAT the help center covers and WHO sees each part.
 * All prose lives in lib/i18n/{locale}/docs.json under mirrored keys —
 * this file only declares structure (sections → articles → step counts),
 * the permission gating (same permissions as nav-config) and which
 * illustrative figure an article renders.
 */

export type FigureId =
  | "workspace"
  | "roles"
  | "yearLifecycle"
  | "matrix"
  | "attendance"
  | "invoice"
  | "payrollLifecycle"
  | "employeeForm"
  | "studentForm"

export interface DocArticle {
  key: string
  /** Number of docs.sections.<section>.articles.<key>.step{n} keys. */
  steps: number
  /** Renders docs.sections.<section>.articles.<key>.tip as a callout. */
  tip?: boolean
  figure?: FigureId
}

export interface DocSection {
  key: string
  icon: LucideIcon
  /** Visible when the user holds ANY of these in the active context; undefined = everyone. */
  permissions?: string[]
  /**
   * Relationship-hat gate (ADR-012): visible when the user IS a parent or
   * student — judged from user.is_parent / user.is_student, never staff
   * permissions. Overrides `permissions` when set.
   */
  relationship?: boolean
  /** Visible for the tutor hat (user.is_tutor) — ADR-012, never staff perms. */
  tutorHat?: boolean
  /** The app page this section documents — rendered as an "open page" link. */
  href?: string
  articles: DocArticle[]
}

export const DOC_SECTIONS: DocSection[] = [
  {
    key: "gettingStarted",
    icon: BookOpen,
    articles: [
      { key: "dashboard", steps: 5, tip: true },
      { key: "workspace", steps: 4, figure: "workspace", tip: true },
      { key: "navigation", steps: 4 },
      { key: "globalSearch", steps: 4, tip: true },
      { key: "attachFiles", steps: 4, tip: true },
      { key: "sortTables", steps: 4, tip: true },
      { key: "bulkRows", steps: 5, tip: true },
      { key: "pageSize", steps: 4, tip: true },
      { key: "emptyDropdowns", steps: 3, tip: true },
      { key: "language", steps: 3, tip: true },
      { key: "calendarClock", steps: 3, tip: true },
      { key: "notifications", steps: 4, tip: true },
    ],
  },
  {
    key: "schools",
    icon: School,
    permissions: ["schools.view", "branches.view"],
    href: "/schools",
    articles: [
      { key: "createSchool", steps: 4 },
      { key: "addBranch", steps: 5, tip: true },
      { key: "profileStats", steps: 5, tip: true },
      { key: "branchStatus", steps: 3 },
    ],
  },
  {
    key: "users",
    icon: Users,
    permissions: ["users.view"],
    href: "/users",
    articles: [
      { key: "rolesExplained", steps: 4, figure: "roles", tip: true },
      { key: "addUser", steps: 4 },
      { key: "accountActions", steps: 4 },
      { key: "bulkActions", steps: 6, tip: true },
      { key: "studentParentAccounts", steps: 4, tip: true },
    ],
  },
  {
    key: "academic",
    icon: CalendarDays,
    permissions: ["academic_years.view"],
    href: "/academic",
    articles: [
      { key: "createYear", steps: 5, figure: "yearLifecycle" },
      { key: "lifecycle", steps: 4, tip: true },
      { key: "assignments", steps: 5, figure: "matrix" },
      { key: "cloneCopy", steps: 4, tip: true },
    ],
  },
  {
    key: "sections",
    icon: LayoutGrid,
    permissions: ["sections.view", "sections.view_own"],
    href: "/sections",
    articles: [
      { key: "createSection", steps: 4 },
      { key: "homeroom", steps: 3, tip: true },
      { key: "assignSections", steps: 5, tip: true },
      { key: "bulkAssign", steps: 4, tip: true },
      { key: "classProfile", steps: 4, tip: true },
      { key: "teacherView", steps: 3, tip: true },
    ],
  },
  {
    key: "students",
    icon: GraduationCap,
    permissions: ["students.view"],
    href: "/students",
    articles: [
      { key: "register", steps: 6, figure: "studentForm", tip: true },
      { key: "bulkImport", steps: 6, tip: true },
      { key: "enroll", steps: 5 },
      { key: "pendingActivation", steps: 4, tip: true },
      { key: "guardians", steps: 5, tip: true },
      { key: "healthDocs", steps: 4 },
      { key: "parentsRegister", steps: 3 },
      { key: "portalLogins", steps: 5, tip: true },
    ],
  },
  {
    key: "employees",
    icon: Briefcase,
    permissions: ["employees.view"],
    href: "/employees",
    articles: [
      { key: "createEmployee", steps: 5, figure: "employeeForm" },
      { key: "positions", steps: 4, tip: true },
      { key: "compensation", steps: 4 },
      { key: "teachingLoad", steps: 4, tip: true },
    ],
  },
  {
    key: "hr",
    icon: UserCheck,
    // Every staff member sees this section — self-service leave is for everyone.
    permissions: [
      "employee_attendance.view",
      "leave.view",
      "leave.request_own",
    ],
    href: "/hr/attendance",
    articles: [
      { key: "employeeAttendance", steps: 5, tip: true },
      { key: "requestLeave", steps: 5, tip: true },
      { key: "approveLeave", steps: 5, tip: true },
      { key: "policyHolidays", steps: 4, tip: true },
      { key: "teacherAppraisals", steps: 6, tip: true },
      { key: "hrReports", steps: 4 },
    ],
  },
  {
    key: "payroll",
    icon: HandCoins,
    permissions: ["payroll.view"],
    href: "/payroll",
    articles: [
      { key: "runPayroll", steps: 5, figure: "payrollLifecycle" },
      { key: "approvePay", steps: 4, tip: true },
    ],
  },
  {
    key: "attendance",
    icon: ClipboardCheck,
    permissions: [
      "attendance.view",
      "attendance.view_own",
      "attendance.reports.view",
    ],
    href: "/attendance",
    articles: [
      { key: "record", steps: 6, figure: "attendance", tip: true },
      { key: "reports", steps: 5, tip: true },
      { key: "excuses", steps: 4, tip: true },
    ],
  },
  {
    // RFID attendance hardware + guardian alerts.
    key: "devices",
    icon: ScanLine,
    permissions: ["devices.view", "cards.view"],
    href: "/devices",
    articles: [
      { key: "setupDevice", steps: 5, tip: true },
      { key: "issueCards", steps: 5, tip: true },
      { key: "guardianAlerts", steps: 5, tip: true },
    ],
  },
  {
    key: "grading",
    icon: NotebookPen,
    permissions: ["grades.view", "grades.manage_own"],
    href: "/marklists",
    articles: [
      { key: "gradingSetup", steps: 5, tip: true },
      { key: "continuousAssessment", steps: 6, tip: true },
      { key: "enterMarks", steps: 7, tip: true },
      { key: "approveMarklists", steps: 5, tip: true },
      { key: "enterOnBehalf", steps: 4, tip: true },
      { key: "reportCards", steps: 6, tip: true },
      { key: "rosters", steps: 7, tip: true },
      { key: "gradingAnalytics", steps: 5, tip: true },
      { key: "marklistAnalysis", steps: 5, tip: true },
      { key: "submissionMonitor", steps: 5, tip: true },
      { key: "transcripts", steps: 7, tip: true },
    ],
  },
  {
    key: "lessonPlanning",
    icon: Map,
    permissions: ["lesson_plans.view", "lesson_plans.review", "lesson_plans.manage_own"],
    href: "/lesson-plans",
    articles: [
      { key: "annualPlan", steps: 7, tip: true },
      { key: "myDay", steps: 5, tip: true },
      { key: "weeklyPlan", steps: 6, tip: true },
      { key: "coverage", steps: 4, tip: true },
      { key: "reviewPlans", steps: 5, tip: true },
      { key: "pacing", steps: 4, tip: true },
    ],
  },
  {
    key: "timetable",
    icon: CalendarClock,
    permissions: ["timetable.view"],
    href: "/timetable",
    articles: [
      { key: "firstSetup", steps: 4, tip: true },
      { key: "periodSchedule", steps: 4, tip: true },
      { key: "build", steps: 6, tip: true },
      { key: "publish", steps: 3, tip: true },
    ],
  },
  {
    key: "promotion",
    icon: TrendingUp,
    permissions: ["promotion.manage"],
    href: "/academic/promotion",
    articles: [
      { key: "board", steps: 6, tip: true },
      { key: "rollover", steps: 5, tip: true },
      { key: "revert", steps: 4, tip: true },
    ],
  },
  {
    key: "transfers",
    icon: ArrowLeftRight,
    permissions: ["transfers.manage"],
    href: "/transfers",
    articles: [
      { key: "request", steps: 6, tip: true },
      { key: "decide", steps: 5, tip: true },
      { key: "applications", steps: 5, tip: true },
      { key: "withdraw", steps: 5, tip: true },
      { key: "letter", steps: 3, tip: true },
      { key: "afterTransfer", steps: 4, tip: true },
    ],
  },
  {
    key: "fees",
    icon: Wallet,
    permissions: ["fees.view"],
    href: "/fees",
    articles: [
      { key: "bankAccounts", steps: 4, tip: true },
      { key: "accountReports", steps: 4, tip: true },
      { key: "structures", steps: 6 },
      { key: "invoices", steps: 6, figure: "invoice" },
      { key: "invoiceDetails", steps: 4, tip: true },
      { key: "notify", steps: 4, tip: true },
      { key: "payments", steps: 5, tip: true },
      { key: "receipts", steps: 4, tip: true },
      { key: "recurringBilling", steps: 5, tip: true },
      { key: "reminders", steps: 4, tip: true },
      { key: "penalties", steps: 4, tip: true },
      { key: "scholarships", steps: 4, tip: true },
      { key: "concessions", steps: 6, tip: true },
      { key: "feeReports", steps: 5, tip: true },
      { key: "books", steps: 8, tip: true },
      { key: "documents", steps: 5, tip: true },
    ],
  },
  {
    // The school store: visible to anyone who can at least request items.
    key: "inventory",
    icon: Boxes,
    permissions: [
      "inventory.view",
      "inventory.manage",
      "inventory.approve",
      "inventory.request",
    ],
    href: "/inventory",
    articles: [
      { key: "overview", steps: 4, tip: true },
      { key: "requestItems", steps: 5, tip: true },
      { key: "runTheStore", steps: 6, tip: true },
      { key: "approvals", steps: 4, tip: true },
      { key: "purchaseOrders", steps: 5, tip: true },
      { key: "stockTakes", steps: 5, tip: true },
      { key: "assetRegister", steps: 6, tip: true },
      { key: "textbooks", steps: 5, tip: true },
    ],
  },
  {
    // Temari.et staff only: the platform seed-data studio.
    key: "catalogs",
    icon: LibraryBig,
    permissions: ["catalogs.manage"],
    href: "/catalogs",
    articles: [
      { key: "overview", steps: 4, tip: true },
      { key: "manageCatalog", steps: 5, tip: true },
      { key: "gradeLadder", steps: 5, tip: true },
      { key: "reviewDirectory", steps: 4, tip: true },
      { key: "smsWhitelist", steps: 4, tip: true },
    ],
  },
  {
    // The LMS (ADR-016): materials, homework and online exams for classes.
    key: "lms",
    icon: BookOpenCheck,
    permissions: ["lms.view", "lms.manage", "lms.manage_own"],
    href: "/lms/exams",
    articles: [
      { key: "questionBanks", steps: 5, tip: true },
      { key: "richQuestions", steps: 5, tip: true },
      { key: "createExam", steps: 6, tip: true },
      { key: "paperParts", steps: 6, tip: true },
      { key: "monitorGrading", steps: 5, tip: true },
      { key: "homework", steps: 5, tip: true },
      { key: "assignmentKinds", steps: 5, tip: true },
      { key: "courses", steps: 6, tip: true },
      { key: "materials", steps: 4, tip: true },
    ],
  },
  {
    // Chat (ADR-019): staff and families alike — never permission-gated;
    // the engine scopes everything per conversation.
    key: "chat",
    icon: MessagesSquare,
    href: "/messages",
    articles: [
      { key: "basics", steps: 5, tip: true },
      { key: "familyChat", steps: 6, tip: true },
      { key: "communicationBook", steps: 5, tip: true },
      { key: "messageTemplates", steps: 5, tip: true },
      { key: "channels", steps: 6, tip: true },
      { key: "voiceAndFiles", steps: 4, tip: true },
    ],
  },
  {
    // Temari AI — every audience has a lane; articles explain each.
    key: "temariAi",
    icon: Sparkles,
    href: "/ai",
    articles: [
      { key: "chat", steps: 5, tip: true },
      { key: "examBuilder", steps: 5, tip: true },
      { key: "sendMessages", steps: 5, tip: true },
      { key: "familyAi", steps: 4, tip: true },
      { key: "schoolAi", steps: 4, tip: true },
      { key: "subscription", steps: 4, tip: true },
    ],
  },
  {
    // National exam prep — every signed-in user, school or none.
    key: "examPrep",
    icon: Target,
    href: "/me/exam-prep",
    articles: [
      { key: "practice", steps: 5, tip: true },
      { key: "papers", steps: 4, tip: true },
    ],
  },
  {
    // Relationship lane: parents and students, never permission-gated.
    key: "family",
    icon: Heart,
    relationship: true,
    href: "/me/children",
    articles: [
      { key: "parentPortal", steps: 5, tip: true },
      { key: "results", steps: 4, tip: true },
      { key: "attendance", steps: 5, tip: true },
      { key: "absenceExcuse", steps: 4, tip: true },
      { key: "paymentsHub", steps: 4, tip: true },
      { key: "verifyPayment", steps: 5, tip: true },
      { key: "feeReminders", steps: 3, tip: true },
      { key: "calendar", steps: 3, tip: true },
      { key: "transfers", steps: 5, tip: true },
      { key: "studentPortal", steps: 4 },
      { key: "classwork", steps: 6, tip: true },
      { key: "classPlan", steps: 4, tip: true },
      { key: "takeCourses", steps: 4, tip: true },
      { key: "hireTutor", steps: 5, tip: true },
      { key: "tutorPayments", steps: 4, tip: true },
      { key: "childLearning", steps: 3 },
      { key: "preferences", steps: 3 },
    ],
  },
  {
    // The tutor hat (ADR-012): anyone with a tutor profile, any status.
    key: "tutoring",
    icon: GraduationCap,
    tutorHat: true,
    href: "/tutoring",
    articles: [
      { key: "becomeTutor", steps: 5, tip: true },
      { key: "monthlyEscrow", steps: 5, tip: true },
      { key: "runSessions", steps: 5, tip: true },
      { key: "earningsPayouts", steps: 5, tip: true },
      { key: "boostProfile", steps: 3, tip: true },
    ],
  },
  {
    // Temari.et marketplace operators.
    key: "marketplaceAdmin",
    icon: HandCoins,
    permissions: ["tutors.review", "marketplace.manage", "gateways.manage"],
    href: "/marketplace/tutors",
    articles: [
      { key: "reviewTutors", steps: 5, tip: true },
      { key: "moneyConsole", steps: 5, tip: true },
      { key: "gatewaysSetup", steps: 4, tip: true },
    ],
  },
]

export function visibleDocSections(
  permissions: string[],
  hats: { isParent?: boolean; isStudent?: boolean; isTutor?: boolean } = {},
  surface: "staff" | "family" | "tutor" = "staff"
): DocSection[] {
  return DOC_SECTIONS.filter((section) => {
    // One workspace, one lane (same rule as the nav): the Help page shows
    // only the active workspace's guides — a dual-hat director reads the
    // family guides inside My family, never merged into the staff docs.
    if (surface === "tutor") return section.tutorHat === true && hats.isTutor === true
    if (surface === "family")
      return (
        section.relationship === true &&
        (hats.isParent === true || hats.isStudent === true)
      )
    if (section.tutorHat || section.relationship) return false
    return (
      !section.permissions ||
      section.permissions.some((p) => permissions.includes(p))
    )
  })
}

/* ------------------------------------------------------------------ */
/* Role quick starts                                                    */
/* ------------------------------------------------------------------ */

export type QuickstartKey =
  | "platform"
  | "principal"
  | "director"
  | "registrar"
  | "financeOfficer"
  | "teacher"
  | "default"

export interface Quickstart {
  key: QuickstartKey
  /** Each item is docs.quickstart.<key>.item{n}; `section` deep-links into the docs. */
  items: { section: string }[]
}

export const QUICKSTARTS: Record<QuickstartKey, Quickstart> = {
  platform: {
    key: "platform",
    items: [
      { section: "schools" },
      { section: "users" },
      { section: "gettingStarted" },
      { section: "academic" },
    ],
  },
  principal: {
    key: "principal",
    items: [
      { section: "schools" },
      { section: "users" },
      { section: "employees" },
      { section: "academic" },
    ],
  },
  director: {
    key: "director",
    items: [
      { section: "academic" },
      { section: "sections" },
      { section: "employees" },
      { section: "hr" },
      { section: "students" },
      { section: "fees" },
    ],
  },
  registrar: {
    key: "registrar",
    items: [
      { section: "students" },
      { section: "sections" },
      { section: "attendance" },
    ],
  },
  financeOfficer: {
    key: "financeOfficer",
    items: [{ section: "fees" }, { section: "payroll" }],
  },
  teacher: {
    key: "teacher",
    items: [
      { section: "gettingStarted" },
      { section: "attendance" },
      { section: "sections" },
      { section: "hr" },
    ],
  },
  default: {
    key: "default",
    items: [{ section: "gettingStarted" }],
  },
}

/** Staff roles ordered by seniority — the first match picks the quick start. */
const ROLE_TO_QUICKSTART: [string, QuickstartKey][] = [
  ["principal", "principal"],
  ["school_admin", "principal"],
  ["director", "director"],
  ["registrar", "registrar"],
  ["finance_officer", "financeOfficer"],
  ["teacher", "teacher"],
]

export function quickstartForRoles(
  roles: string[],
  isPlatform: boolean
): Quickstart {
  if (isPlatform) return QUICKSTARTS.platform
  for (const [role, key] of ROLE_TO_QUICKSTART) {
    if (roles.includes(role)) return QUICKSTARTS[key]
  }
  return QUICKSTARTS.default
}
