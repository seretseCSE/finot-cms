"use client"

import { Globe, GraduationCap, HeartHandshake, School, Users } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { useTranslation } from "@/lib/i18n"
import { roleLabel } from "@/lib/roles"
import type { AdminUser, UserAffiliation } from "@/lib/types"
import { cn } from "@/lib/utils"

/** How many school blocks to render inline before collapsing into a popover. */
const MAX_INLINE_SCHOOLS = 2

/** A single school → its branches → their roles. */
function SchoolBlock({ affiliation }: { affiliation: UserAffiliation }) {
  const { t } = useTranslation("users")

  return (
    <div className="space-y-1">
      <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
        <School className="size-3.5 shrink-0 text-muted-foreground" />
        <span className="text-sm font-medium leading-tight">{affiliation.school_name}</span>
        {affiliation.roles.map((r) => (
          <Badge key={r} variant="secondary" className="px-1.5 py-0 text-[11px] capitalize">
            {roleLabel(r)}
          </Badge>
        ))}
      </div>

      {affiliation.branches.length > 0 && (
        <ul className="ml-[7px] space-y-0.5 border-l pl-3">
          {affiliation.branches.map((b) => (
            <li key={b.id} className="flex flex-wrap items-baseline gap-x-1.5 text-sm leading-tight">
              <span
                className={cn(
                  "size-1.5 shrink-0 self-center rounded-full",
                  b.active ? "bg-emerald-500" : "bg-red-500",
                )}
                title={b.active ? t("status.active") : t("status.inactive")}
              />
              <span className={cn("font-medium", !b.active && "text-muted-foreground")}>{b.name}</span>
              {b.roles.length > 0 && (
                <span className="text-xs text-muted-foreground capitalize">
                  {b.roles.map(roleLabel).join(", ")}
                </span>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

/**
 * Relationship-lane rows (ADR-012): a student's enrollment or a parent's
 * children — access that exists without any membership. Rendered as tinted
 * chips so staff can tell at a glance WHY this account exists.
 */
function RelationshipRows({ user }: { user: AdminUser }) {
  const { t } = useTranslation("users")
  const student = user.relationships?.student
  const parent = user.relationships?.parent

  if (!student && !parent) return null

  return (
    <>
      {student ? (
        <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
          <GraduationCap className="size-3.5 shrink-0 text-info" />
          <Badge variant="secondary" className="bg-info/10 px-1.5 py-0 text-[11px] text-info">
            {t("access.student")}
          </Badge>
          <span className="text-xs text-muted-foreground">
            {[student.school_name, student.branch_name, student.grade].filter(Boolean).join(" · ")}
          </span>
        </div>
      ) : null}
      {parent ? (
        <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
          <HeartHandshake className="size-3.5 shrink-0 text-primary" />
          <Badge variant="secondary" className="bg-primary/10 px-1.5 py-0 text-[11px] text-primary">
            {t("access.parent")}
          </Badge>
          <span className="text-xs text-muted-foreground" title={parent.children.join(", ")}>
            {[
              parent.children_count === 1
                ? parent.children[0] || t("access.child")
                : t("access.children", { count: parent.children_count }),
              parent.schools.join(", "),
            ]
              .filter(Boolean)
              .join(" · ")}
          </span>
        </div>
      ) : null}
    </>
  )
}

/** The complete affiliation tree — schools, platform roles, and loose roles. */
function AffiliationTree({ user }: { user: AdminUser }) {
  const { t } = useTranslation("users")

  return (
    <div className="space-y-2.5">
      {user.affiliations.map((a) => (
        <SchoolBlock key={a.school_id} affiliation={a} />
      ))}

      {user.platform_roles.length > 0 && (
        <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
          <Globe className="size-3.5 shrink-0 text-primary" />
          <span className="text-xs font-medium text-muted-foreground">{t("access.platform")}</span>
          {user.platform_roles.map((r) => (
            <Badge key={r} variant="secondary" className="px-1.5 py-0 text-[11px] capitalize">
              {roleLabel(r)}
            </Badge>
          ))}
        </div>
      )}

      {user.other_roles.length > 0 && (
        <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
          <Users className="size-3.5 shrink-0 text-muted-foreground" />
          {user.other_roles.map((r) => (
            <Badge key={r} variant="outline" className="px-1.5 py-0 text-[11px] capitalize">
              {roleLabel(r)}
            </Badge>
          ))}
        </div>
      )}

      <RelationshipRows user={user} />
    </div>
  )
}

/**
 * Unified "Access" cell: shows exactly which role a user holds in which
 * school/branch, instead of three disconnected roles/schools/branches columns.
 * Overflowing schools collapse behind a "+N" popover to keep rows compact.
 */
export function AffiliationsCell({ user }: { user: AdminUser }) {
  const { t } = useTranslation("users")

  const hasRelationships = Boolean(user.relationships?.student || user.relationships?.parent)
  const hasAny =
    user.affiliations.length > 0 ||
    user.platform_roles.length > 0 ||
    user.other_roles.length > 0 ||
    hasRelationships

  if (!hasAny) return <span className="text-sm text-muted-foreground">—</span>

  const inline = user.affiliations.slice(0, MAX_INLINE_SCHOOLS)
  const overflow = user.affiliations.length - inline.length

  // Platform / loose roles are compact, so only schools drive the inline cap.
  const showTailInline = overflow === 0

  return (
    <div className="space-y-2.5 py-0.5">
      {inline.map((a) => (
        <SchoolBlock key={a.school_id} affiliation={a} />
      ))}

      {showTailInline ? (
        <>
          {user.platform_roles.length > 0 && (
            <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
              <Globe className="size-3.5 shrink-0 text-primary" />
              <span className="text-xs font-medium text-muted-foreground">{t("access.platform")}</span>
              {user.platform_roles.map((r) => (
                <Badge key={r} variant="secondary" className="px-1.5 py-0 text-[11px] capitalize">
                  {roleLabel(r)}
                </Badge>
              ))}
            </div>
          )}
          {user.other_roles.length > 0 && (
            <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
              <Users className="size-3.5 shrink-0 text-muted-foreground" />
              {user.other_roles.map((r) => (
                <Badge key={r} variant="outline" className="px-1.5 py-0 text-[11px] capitalize">
                  {roleLabel(r)}
                </Badge>
              ))}
            </div>
          )}
          <RelationshipRows user={user} />
        </>
      ) : (
        <Popover>
          <PopoverTrigger asChild>
            <button
              type="button"
              className="text-xs font-medium text-primary hover:underline"
              onClick={(e) => e.stopPropagation()}
            >
              {t("access.more", { count: overflow })}
            </button>
          </PopoverTrigger>
          <PopoverContent
            align="start"
            className="max-h-80 w-72 overflow-y-auto"
            onClick={(e) => e.stopPropagation()}
          >
            <AffiliationTree user={user} />
          </PopoverContent>
        </Popover>
      )}
    </div>
  )
}

/** Flattened, human-readable rendering of a user's access for CSV / Excel export. */
export function affiliationExport(user: AdminUser): string {
  const parts = user.affiliations.map((a) => {
    const schoolRoles = a.roles.map(roleLabel)
    const branches = a.branches.map(
      (b) => `${b.name} (${b.roles.map(roleLabel).join(", ")})${b.active ? "" : " [inactive]"}`,
    )
    const detail = [...schoolRoles, ...branches].join(", ")
    return detail ? `${a.school_name}: ${detail}` : a.school_name
  })

  if (user.platform_roles.length > 0) {
    parts.push(`Platform: ${user.platform_roles.map(roleLabel).join(", ")}`)
  }
  if (user.other_roles.length > 0) {
    parts.push(user.other_roles.map(roleLabel).join(", "))
  }
  if (user.relationships?.student) {
    const s = user.relationships.student
    parts.push(
      `Student: ${[s.school_name, s.branch_name, s.grade].filter(Boolean).join(" · ") || "—"}`,
    )
  }
  if (user.relationships?.parent) {
    const p = user.relationships.parent
    parts.push(`Parent: ${p.children.join(", ") || p.children_count}`)
  }

  return parts.join(" | ")
}
