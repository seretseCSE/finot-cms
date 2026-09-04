"use client"

import {
  Briefcase,
  CalendarDays,
  ClipboardCheck,
  GraduationCap,
  ListChecks,
  School,
  Users,
  Wallet,
  type LucideIcon,
} from "lucide-react"
import Link from "next/link"

import { useTranslation } from "@/lib/i18n"

interface QuickAction {
  href: string
  icon: LucideIcon
  titleKey: string
  descKey: string
  permission?: string
}

const QUICK_ACTIONS: QuickAction[] = [
  {
    href: "/students/new",
    icon: GraduationCap,
    titleKey: "actRegisterStudent",
    descKey: "actRegisterStudentDesc",
    permission: "students.create",
  },
  {
    href: "/attendance",
    icon: ClipboardCheck,
    titleKey: "actAttendance",
    descKey: "actAttendanceDesc",
    permission: "attendance.record_own",
  },
  {
    href: "/attendance",
    icon: ClipboardCheck,
    titleKey: "actAttendance",
    descKey: "actAttendanceDesc",
    permission: "attendance.record",
  },
  {
    href: "/marklists",
    icon: ListChecks,
    titleKey: "actMarklists",
    descKey: "actMarklistsDesc",
    permission: "grades.manage_own",
  },
  {
    href: "/invoices",
    icon: Wallet,
    titleKey: "actFees",
    descKey: "actFeesDesc",
    permission: "fees.manage",
  },
  {
    href: "/employees",
    icon: Briefcase,
    titleKey: "actEmployee",
    descKey: "actEmployeeDesc",
    permission: "employees.create",
  },
  {
    href: "/users",
    icon: Users,
    titleKey: "actUsers",
    descKey: "actUsersDesc",
    permission: "users.view",
  },
  {
    href: "/academic",
    icon: CalendarDays,
    titleKey: "actAcademic",
    descKey: "actAcademicDesc",
    permission: "academic_years.view",
  },
  {
    href: "/schools",
    icon: School,
    titleKey: "actSchools",
    descKey: "actSchoolsDesc",
    permission: "schools.view",
  },
]

/** The shortcut grid at the foot of the dashboard, gated per permission. */
export function QuickActions({ permissions }: { permissions: string[] }) {
  const { t } = useTranslation("common")

  // Deduplicate by href (attendance appears for either lane).
  const seen = new Set<string>()
  const actions = QUICK_ACTIONS.filter((action) => {
    if (action.permission && !permissions.includes(action.permission))
      return false
    if (seen.has(action.href)) return false
    seen.add(action.href)
    return true
  }).slice(0, 6)

  if (actions.length === 0) return null

  return (
    <section>
      <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t("dashboard.quickActions")}
      </h2>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {actions.map((action) => {
          const Icon = action.icon
          return (
            <Link
              key={`${action.href}-${action.titleKey}`}
              href={action.href}
              className="group pressable flex items-center gap-3.5 rounded-2xl border bg-card p-4 shadow-xs transition-all hover:border-primary/30 hover:shadow-sm"
            >
              <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors duration-200 group-hover:bg-primary group-hover:text-primary-foreground">
                <Icon className="size-[18px]" strokeWidth={1.75} />
              </span>
              <span className="min-w-0">
                <span className="block truncate text-sm font-medium">
                  {t(`dashboard.${action.titleKey}`)}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                  {t(`dashboard.${action.descKey}`)}
                </span>
              </span>
            </Link>
          )
        })}
      </div>
    </section>
  )
}
