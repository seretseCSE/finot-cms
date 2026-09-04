import {
  ArrowLeftRight,
  Sparkles,
  BadgePercent,
  Landmark,
  SlidersHorizontal,
  CalendarClock,
  LibraryBig,
  LayoutDashboard,
  TrendingUp,
  School,
  GitBranch,
  Users,
  CalendarCheck2,
  CalendarDays,
  CalendarRange,
  LayoutGrid,
  GraduationCap,
  MessagesSquare,
  Briefcase,
  ClipboardCheck,
  HandCoins,
  Wallet,
  Receipt,
  UserCheck,
  CalendarOff,
  BarChart3,
  CircleUser,
  BookOpen,
  Boxes,
  NotebookPen,
  ListChecks,
  Map,
  FileBadge,
  FileCheck2,
  Percent,
  ScanLine,
  ScrollText,
  Table2,
  Target,
  FileQuestion,
  BookOpenCheck,
  FolderOpen,
  DatabaseZap,
  type LucideIcon,
} from "lucide-react"

export interface NavItem {
  key: string
  href: string
  icon: LucideIcon
  /** Permission required to see this item; undefined = always visible. */
  permission?: string
  /**
   * ANY-OF alternative to `permission` for pages with a supervisory and an
   * ownership lane (e.g. sections.view / sections.view_own) — visible when
   * the user holds at least one.
   */
  anyPermission?: string[]
  /**
   * Relationship-hat gate (ADR-012): shown when the user IS a parent/student
   * ("family" = either hat), judged from user.is_parent / user.is_student —
   * never from staff permissions. Items with this set ignore `permission`.
   *   - "guest"   → pure B2C learner: no family hat AND no staff membership.
   *   - "learner" → any learner surface: family OR pure B2C, but NOT
   *                 staff-only accounts (school staff don't get exam prep).
   */
  relationship?: "parent" | "student" | "family" | "guest" | "learner" | "tutor"
  /**
   * Hidden for relationship-only accounts (parents/students with no staff
   * membership) — their Home is the family surface, not the staff dashboard.
   */
  staffOnly?: boolean
  /**
   * Hidden when the user ALSO holds this permission — for pages that a more
   * capable role reaches through another surface (e.g. Branch settings lives
   * inside the branch profile for holders of Branch Management).
   */
  hideWithPermission?: string
}

/**
 * The active workspace's SURFACE — one nav lane at a time, never a merge.
 * A director who is also a parent sees the staff nav inside the school
 * workspace and the family nav inside "My family"; switching workspaces is
 * the only way to see the other lane.
 *   - "staff"  → the school/branch/platform workspace (permission-gated nav)
 *   - "family" → the relationship lane (parent/student hats + B2C learners)
 *   - "tutor"  → the tutor workspace
 */
export type WorkspaceSurface = "staff" | "family" | "tutor"

export interface NavSection {
  labelKey: string
  items: NavItem[]
  /**
   * Small "pinned" groups (Overview, family lane, exam prep) render open and
   * un-collapsible; large domain groups (Academics, Finance…) get a collapse
   * chevron so heavy roles can fold away what they don't use.
   */
  pinned?: boolean
}

// SECTION ORDER drives the mobile bottom bar: the first 4 VISIBLE items across
// all sections become the tab bar, the rest fall into the grouped Menu sheet.
// Because items are permission-filtered per role, ordering People/Academics
// ahead of Finance means an admin lands on Students tabs, a teacher on their
// Academics tabs, and a finance officer (who sees neither) on Finance tabs —
// each role gets sensible tabs from ONE static order.
export const NAV_SECTIONS: NavSection[] = [
  {
    labelKey: "sections.dashboard",
    pinned: true,
    // Settings + help moved out of the nav: they live in the profile
    // dropdown (desktop sidebar footer) and the mobile Menu sheet.
    items: [
      { key: "dashboard", href: "/dashboard", icon: LayoutDashboard, staffOnly: true },
      // Chat (ADR-019): any staff membership grants participation — no
      // permission gate; the backend scopes everything per conversation.
      { key: "messages", href: "/messages", icon: MessagesSquare, staffOnly: true },
      // Temari AI (staff lanes): visible for any role that maps to an AI
      // lane (mirrors AiLane::availableFor on the backend). Entitlement
      // (School Plan vs teaser quota) is enforced per prompt server-side.
      {
        key: "temariAi",
        href: "/ai",
        icon: Sparkles,
        staffOnly: true,
        anyPermission: [
          "reports.view",
          "grades.manage",
          "lesson_plans.review",
          "grades.manage_own",
          "lesson_plans.manage_own",
          "lms.manage_own",
          "students.create",
          "transfers.manage",
          "fees.reports.view",
          "finance.books.view",
          "platform.access",
        ],
      },
    ],
  },
  {
    // The relationship lane (ADR-012): visible only for parent/student hats.
    // ORDER MATTERS on mobile — the bottom bar shows the first 4 visible
    // items, so each hat's essentials must come first: a pure student gets
    // Home / Assignments / Exams / Results, a pure parent gets
    // Home / Results / Attendance / Payments.
    labelKey: "sections.me",
    pinned: true,
    items: [
      { key: "myHome", href: "/me", icon: LayoutDashboard, relationship: "family" },
      // Messages earns a first-four (bottom bar) slot: teacher↔family chat is
      // the most-touched family surface after Home.
      { key: "messages", href: "/messages", icon: MessagesSquare, relationship: "family" },
      // Temari AI (student tutor / parent assistant) — earns a bottom-bar
      // slot: it is the family lane's flagship feature. The surface param
      // pins the FAMILY assistant for users who also hold a staff workspace
      // (the workspace decides the assistant — there is no in-chat picker).
      { key: "temariAi", href: "/ai?surface=family", icon: Sparkles, relationship: "learner" },
      {
        key: "myAssignments",
        href: "/me/assignments",
        icon: NotebookPen,
        relationship: "student",
      },
      { key: "myExams", href: "/me/exams", icon: FileQuestion, relationship: "student" },
      { key: "myResults", href: "/me/results", icon: FileBadge, relationship: "family" },
      {
        key: "myAttendance",
        href: "/me/attendance",
        icon: CalendarCheck2,
        relationship: "family",
      },
      { key: "myPayments", href: "/me/payments", icon: Receipt, relationship: "parent" },
      { key: "myMaterials", href: "/me/materials", icon: FolderOpen, relationship: "student" },
      { key: "myCourses", href: "/me/courses", icon: LibraryBig, relationship: "student" },
      {
        key: "childLearning",
        href: "/me/children/learning",
        icon: BookOpenCheck,
        relationship: "parent",
      },
      {
        // The class plan (approved lesson plans): syllabus progress + this
        // week's topics per subject.
        key: "myClassPlan",
        href: "/me/lesson-plans",
        icon: Map,
        relationship: "family",
      },
      {
        key: "myTimetable",
        href: "/me/timetable",
        icon: CalendarClock,
        relationship: "family",
      },
      {
        key: "myCalendar",
        href: "/me/calendar",
        icon: CalendarDays,
        relationship: "family",
      },
      {
        key: "myTransfers",
        href: "/me/transfers",
        icon: ArrowLeftRight,
        relationship: "family",
      },
      // Hiring a private tutor is a family/learner concern: requests,
      // engagements, monthly payments, session confirmations.
      {
        key: "myTutoring",
        href: "/me/tutoring",
        icon: BookOpen,
        relationship: "learner",
      },
    ],
  },
  {
    // The tutor workspace (ADR-012 tutor hat): visible the moment a user has
    // a tutor profile in ANY status — the pages themselves adapt (apply →
    // pending → approved).
    labelKey: "sections.tutoring",
    pinned: true,
    items: [
      { key: "tutorHome", href: "/tutoring", icon: LayoutDashboard, relationship: "tutor" },
      { key: "tutorRequests", href: "/tutoring/requests", icon: ListChecks, relationship: "tutor" },
      {
        key: "tutorEngagements",
        href: "/tutoring/engagements",
        icon: GraduationCap,
        relationship: "tutor",
      },
      { key: "tutorEarnings", href: "/tutoring/earnings", icon: Wallet, relationship: "tutor" },
    ],
  },
  {
    // National exam prep (ADR-016): a LEARNER surface — school students,
    // parents, and no-school B2C learners. School staff (teacher/admin/etc.)
    // do NOT see it unless they also wear a family hat.
    labelKey: "sections.examPrep",
    pinned: true,
    items: [
      { key: "examPrep", href: "/me/exam-prep", icon: Target, relationship: "learner" },
      { key: "prepCourses", href: "/me/courses", icon: LibraryBig, relationship: "guest" },
    ],
  },
  {
    // People come first among the staff domains: Students/Parents are the
    // most broadly touched records, so admins & registrars land on them in
    // the mobile tab bar. Teachers (no people.view) fall through to Academics.
    labelKey: "sections.people",
    items: [
      { key: "students", href: "/students", icon: GraduationCap, permission: "students.view" },
      { key: "parents", href: "/parents", icon: Users, permission: "guardians.view" },
      {
        key: "transfers",
        href: "/transfers",
        icon: ArrowLeftRight,
        permission: "transfers.manage",
      },
    ],
  },
  {
    // Academic STRUCTURE only — the year/term/section/timetable skeleton plus
    // lesson planning. Grades & report outputs live in their own group below
    // so this list stays scannable (was 13 items crammed together).
    labelKey: "sections.academic",
    items: [
      {
        key: "academicYears",
        href: "/academic",
        icon: CalendarDays,
        permission: "academic_years.view",
      },
      {
        key: "semesters",
        href: "/semesters",
        icon: CalendarRange,
        permission: "academic_years.view",
      },
      {
        key: "sections",
        href: "/sections",
        icon: LayoutGrid,
        anyPermission: ["sections.view", "sections.view_own"],
      },
      {
        key: "timetable",
        href: "/timetable",
        icon: CalendarClock,
        permission: "timetable.view",
      },
      {
        // Lesson planning: teachers author (manage_own), directors/principals
        // review — the same page adapts via tabs.
        key: "lessonPlans",
        href: "/lesson-plans",
        icon: Map,
        anyPermission: ["lesson_plans.view", "lesson_plans.review", "lesson_plans.manage_own"],
      },
    ],
  },
  {
    // Grades & academic reporting: continuous assessment through frozen report
    // cards, rosters, transcripts and promotion — the assessment lifecycle.
    labelKey: "sections.assessment",
    items: [
      {
        key: "continuousAssessment",
        href: "/academic/continuous-assessments",
        icon: NotebookPen,
        permission: "grades.manage",
      },
      {
        key: "marklists",
        href: "/marklists",
        icon: ListChecks,
        anyPermission: ["grades.view", "grades.manage_own"],
      },
      {
        // The results hub: the classic Ethiopian roster sheets (semester +
        // yearly) with report-card printing, conduct entry and the
        // extra-assessment checklist. Homeroom teachers see their own
        // sections (backend scopes the rows). The old report-cards page
        // redirects here.
        key: "rosters",
        href: "/academic/rosters",
        icon: Table2,
        anyPermission: ["grades.view", "grades.manage_own"],
      },
      {
        // Transcript register: view/print/export official transcripts in
        // bulk. Homeroom teachers get their own sections only.
        key: "transcripts",
        href: "/academic/transcripts",
        icon: ScrollText,
        anyPermission: ["grades.view", "grades.manage_own"],
      },
      {
        key: "gradingReports",
        href: "/academic/grading-reports",
        icon: BarChart3,
        permission: "grades.view",
      },
      {
        key: "grading",
        href: "/academic/grading",
        icon: Percent,
        permission: "grades.manage",
      },
      {
        key: "promotion",
        href: "/academic/promotion",
        icon: TrendingUp,
        permission: "promotion.manage",
      },
    ],
  },
  {
    // The LMS (ADR-016). Teachers reach the same pages through their
    // ownership lane (lms.manage_own — the backend scopes rows to their own
    // classes); supervisors see the whole branch/school.
    labelKey: "sections.learning",
    items: [
      {
        key: "lmsExams",
        href: "/lms/exams",
        icon: FileQuestion,
        anyPermission: ["lms.view", "lms.manage_own"],
      },
      {
        key: "lmsAssignments",
        href: "/lms/assignments",
        icon: BookOpenCheck,
        anyPermission: ["lms.view", "lms.manage_own"],
      },
      {
        key: "lmsCourses",
        href: "/lms/courses",
        icon: LibraryBig,
        anyPermission: ["lms.view", "lms.manage_own", "exam_prep.manage"],
      },
      {
        key: "lmsMaterials",
        href: "/lms/materials",
        icon: FolderOpen,
        anyPermission: ["lms.view", "lms.manage_own"],
      },
      {
        key: "questionBanks",
        href: "/lms/question-banks",
        icon: DatabaseZap,
        anyPermission: ["lms.manage", "lms.manage_own", "exam_prep.manage"],
      },
    ],
  },
  {
    // Attendance is its own domain — the daily register, its reports, parent
    // excuses, and the RFID readers/cards that feed it. (Split out of the old
    // catch-all "Operations" group.)
    labelKey: "sections.attendance",
    items: [
      {
        key: "attendance",
        href: "/attendance",
        icon: ClipboardCheck,
        anyPermission: ["attendance.view", "attendance.view_own"],
      },
      {
        // Supervisors read their whole scope; homeroom teachers open the
        // same page capped to their own sections (attendance.view_own).
        key: "attendanceReports",
        href: "/attendance/reports",
        icon: BarChart3,
        anyPermission: ["attendance.reports.view", "attendance.view_own"],
      },
      {
        // Parent-filed absence excuses: the review queue + decision.
        key: "absenceExcuses",
        href: "/attendance/excuses",
        icon: FileCheck2,
        anyPermission: ["attendance.record", "attendance.view"],
      },
      { key: "devices", href: "/devices", icon: ScanLine, permission: "devices.view" },
    ],
  },
  {
    // All money in one place — fees, invoices, collection accounts, discounts,
    // fee reports and the cashbook. (Split out of the old "Operations" group.)
    labelKey: "sections.finance",
    items: [
      { key: "fees", href: "/fees", icon: Wallet, permission: "fees.view" },
      { key: "invoices", href: "/invoices", icon: Receipt, permission: "fees.view" },
      {
        key: "paymentAccounts",
        href: "/payment-accounts",
        icon: Landmark,
        permission: "fees.view",
      },
      {
        key: "concessions",
        href: "/concessions",
        icon: BadgePercent,
        permission: "fees.view",
      },
      {
        key: "feeReports",
        href: "/fees/reports",
        icon: BarChart3,
        permission: "fees.reports.view",
      },
      {
        key: "financeBooks",
        href: "/finance",
        icon: BookOpen,
        permission: "finance.books.view",
      },
      {
        // The school store: the hub adapts per role — storekeepers run it,
        // approvers decide, every other staff member just requests items.
        key: "inventory",
        href: "/inventory",
        icon: Boxes,
        anyPermission: ["inventory.view", "inventory.manage", "inventory.approve", "inventory.request"],
      },
    ],
  },
  {
    labelKey: "sections.hr",
    items: [
      { key: "employees", href: "/employees", icon: Briefcase, permission: "employees.view" },
      {
        key: "employeeAttendance",
        href: "/hr/attendance",
        icon: UserCheck,
        permission: "employee_attendance.view",
      },
      { key: "leave", href: "/hr/leave", icon: CalendarOff, permission: "leave.view" },
      { key: "payroll", href: "/payroll", icon: HandCoins, permission: "payroll.view" },
      {
        key: "evaluations",
        href: "/hr/evaluations",
        icon: ClipboardCheck,
        permission: "evaluations.view",
      },
      // The evaluated employee's own lane (the register stays supervisory).
      {
        key: "myEvaluations",
        href: "/hr/evaluations",
        icon: ClipboardCheck,
        permission: "evaluations.view_own",
        hideWithPermission: "evaluations.view",
      },
      { key: "hrReports", href: "/hr/reports", icon: BarChart3, permission: "hr.reports.view" },
      { key: "myHr", href: "/hr/me", icon: CircleUser, permission: "leave.request_own" },
    ],
  },
  {
    // Org administration: schools, branches, user accounts, branch settings.
    labelKey: "sections.administration",
    items: [
      { key: "schools", href: "/schools", icon: School, permission: "schools.view" },
      { key: "branches", href: "/branches", icon: GitBranch, permission: "branches.view" },
      { key: "users", href: "/users", icon: Users, permission: "users.view" },
      {
        key: "branchSettings",
        href: "/branch-settings",
        icon: SlidersHorizontal,
        permission: "branch_settings.manage",
        // School managers tune a branch from its profile's Settings tab —
        // the standalone page stays as the director lane only.
        hideWithPermission: "branches.view",
      },
    ],
  },
  {
    // Temari.et staff only: the seed-data catalog studio (subjects, banks,
    // grade levels, health conditions, school directory).
    labelKey: "sections.platform",
    items: [
      {
        key: "catalogs",
        href: "/catalogs",
        icon: LibraryBig,
        permission: "catalogs.manage",
      },
      // The tutoring marketplace consoles (Temari.et staff): vetting queue,
      // the escrow money console + payout desk, and the gateway matrix.
      {
        key: "marketplaceTutors",
        href: "/marketplace/tutors",
        icon: UserCheck,
        permission: "tutors.review",
      },
      {
        key: "marketplaceMoney",
        href: "/marketplace/money",
        icon: Landmark,
        permission: "marketplace.manage",
      },
      {
        key: "paymentGateways",
        href: "/marketplace/gateways",
        icon: Wallet,
        permission: "gateways.manage",
      },
    ],
  },
]

/**
 * True when `href` is the BEST (longest) nav match for the current path —
 * keeps parent items (/academic) from lighting up on nested pages that have
 * their own nav entry (/academic/promotion).
 */
export function isNavActive(href: string, pathname: string, allHrefs: string[]): boolean {
  // Hrefs may carry a query (/ai?surface=family) — match on the path alone.
  const path = (h: string) => h.split("?")[0]
  const matches = (h: string) => pathname === path(h) || pathname.startsWith(`${path(h)}/`)
  if (!matches(href)) return false
  return !allHrefs.some((other) => path(other).length > path(href).length && matches(other))
}

/**
 * Relationship-lane labels (ADR-012): these are NEVER staff memberships —
 * access derives from profile links (student_guardians, students.user_id,
 * tutor_profiles), so a membership row carrying one of these roles (legacy /
 * bad data) must never make an account count as staff.
 */
const RELATIONSHIP_ROLES = new Set(["student", "parent", "tutor", "vendor"])

interface MembershipLike {
  is_active: boolean
  role?: string
  scope?: string
}

function isStaffMembershipRow(m: MembershipLike): boolean {
  return m.is_active && !RELATIONSHIP_ROLES.has(m.role ?? "") && m.scope !== "relationship"
}

/**
 * A pure relationship-hat account (ADR-012): wears a family hat and holds NO
 * active staff membership. Their Home is /me — the staff dashboard never
 * appears for them.
 */
export function isRelationshipOnly(
  user:
    | { is_parent?: boolean; is_student?: boolean; memberships?: MembershipLike[] }
    | null
    | undefined,
): boolean {
  if (!user) return false
  const familyHat = user.is_parent === true || user.is_student === true
  return familyHat && !(user.memberships ?? []).some(isStaffMembershipRow)
}

/** Holds at least one active staff membership (teacher/admin/finance/…). */
export function hasStaffMembership(
  user: { memberships?: MembershipLike[] } | null | undefined,
): boolean {
  return (user?.memberships ?? []).some(isStaffMembershipRow)
}

export function visibleSections(
  permissions: string[],
  hats: {
    isParent?: boolean
    isStudent?: boolean
    isTutor?: boolean
    relationshipOnly?: boolean
    isStaff?: boolean
  } = {},
  surface: WorkspaceSurface = "staff",
): NavSection[] {
  const isFamily = hats.isParent === true || hats.isStudent === true
  return NAV_SECTIONS.map((section) => ({
    ...section,
    items: section.items.filter((item) => {
      // ONE lane per surface: the active workspace decides which nav exists.
      if (surface === "tutor") return item.relationship === "tutor" && hats.isTutor === true
      if (surface === "family") {
        if (item.relationship === "family") return isFamily
        if (item.relationship === "parent") return hats.isParent === true
        if (item.relationship === "student") return hats.isStudent === true
        // "guest": pure B2C learner only — no family hat AND no staff
        // membership. Students reach the same page under their own section.
        if (item.relationship === "guest") return !isFamily && hats.isStaff !== true
        // "learner": exam prep etc. — family OR pure B2C, but never a
        // staff-only account (school staff don't get the exam-prep lane).
        if (item.relationship === "learner") return isFamily || hats.isStaff !== true
        // Staff/tutor items never leak into the family workspace.
        return false
      }
      // Staff surface: relationship-lane items live in their own workspaces.
      if (item.relationship) return false
      if (item.staffOnly && hats.relationshipOnly === true) return false
      if (item.hideWithPermission && permissions.includes(item.hideWithPermission)) return false
      if (item.anyPermission) return item.anyPermission.some((p) => permissions.includes(p))
      return !item.permission || permissions.includes(item.permission)
    }),
  })).filter((section) => section.items.length > 0)
}
