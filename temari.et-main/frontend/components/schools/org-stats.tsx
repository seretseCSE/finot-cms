"use client"

import {
  BookOpen,
  Briefcase,
  GraduationCap,
  HeartHandshake,
  LayoutGrid,
  Presentation,
} from "lucide-react"
import { useRouter } from "next/navigation"

import { StatCard } from "@/components/ui/stat-card"
import { useTranslation } from "@/lib/i18n"
import type { OrgStats } from "@/lib/types"

/** The vitals tiles a viewer can drill into. */
export type OrgStatKey =
  | "students"
  | "teachers"
  | "employees"
  | "guardians"
  | "sections"
  | "subjects"

/** Which module each tile jumps to; a missing key leaves that tile static. */
export type OrgStatLinks = Partial<Record<OrgStatKey, string>>

/**
 * The module each vitals tile drills into, gated by permission — the list
 * pages scope themselves to the active school/branch context. Tiles with no
 * viewer-facing page (e.g. subjects live in the platform catalog) stay static.
 */
export function orgStatLinks(permissions: string[]): OrgStatLinks {
  return {
    students: permissions.includes("students.view") ? "/students" : undefined,
    teachers: permissions.includes("employees.view") ? "/employees" : undefined,
    employees: permissions.includes("employees.view") ? "/employees" : undefined,
    guardians: permissions.includes("guardians.view") ? "/parents" : undefined,
    sections: permissions.includes("sections.view") ? "/sections" : undefined,
  }
}

/** "KG-1 – Grade 12", a single grade, or null when nothing is set up yet. */
export function formatGradeSpan(min?: string | null, max?: string | null): string | null {
  if (!min && !max) return null
  if (!min || !max || min === max) return min ?? max ?? null
  return `${min} – ${max}`
}

/**
 * The at-a-glance vitals strip for a school/branch profile: six stat tiles.
 * `stats === null` renders skeleton tiles (StatCard handles it per tile).
 * Pass `links` to make tiles tap through to their module.
 */
export function OrgStatTiles({
  stats,
  links,
}: {
  stats: OrgStats | null
  links?: OrgStatLinks
}) {
  const { t } = useTranslation("schools")
  const router = useRouter()

  /** onClick that routes to a tile's module, or undefined when it has none. */
  const go = (key: OrgStatKey) => {
    const href = links?.[key]
    return href ? () => router.push(href) : undefined
  }

  return (
    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6">
      <StatCard
        label={t("stats.students")}
        value={stats ? stats.students.active : null}
        icon={GraduationCap}
        hint={
          stats && stats.students.pending > 0
            ? t("stats.pendingHint", { count: stats.students.pending })
            : undefined
        }
        onClick={go("students")}
      />
      <StatCard
        label={t("stats.teachers")}
        value={stats ? tally(stats, "teacher") : null}
        icon={Presentation}
        hint={
          stats && stats.academics.teachers_teaching > 0
            ? t("stats.teachingHint", { count: stats.academics.teachers_teaching })
            : undefined
        }
        onClick={go("teachers")}
      />
      <StatCard
        label={t("stats.employees")}
        value={stats ? stats.employees.total : null}
        icon={Briefcase}
        onClick={go("employees")}
      />
      <StatCard
        label={t("stats.guardians")}
        value={stats ? stats.guardians : null}
        icon={HeartHandshake}
        onClick={go("guardians")}
      />
      <StatCard
        label={t("stats.sections")}
        value={stats ? stats.academics.sections : null}
        icon={LayoutGrid}
        hint={
          stats && stats.academics.capacity > 0
            ? t("stats.capacityHint", { count: stats.academics.capacity })
            : undefined
        }
        onClick={go("sections")}
      />
      <StatCard
        label={t("stats.subjects")}
        value={stats ? stats.academics.subjects_taught : null}
        icon={BookOpen}
        onClick={go("subjects")}
      />
    </div>
  )
}

function tally(stats: OrgStats, jobTitle: string): number {
  return stats.employees.by_job_title.find((row) => row.job_title === jobTitle)?.total ?? 0
}
