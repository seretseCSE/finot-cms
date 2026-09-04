"use client"

import {
  BookOpen,
  Briefcase,
  FileText,
  GraduationCap,
  KeyRound,
  Pencil,
  UserRound,
} from "lucide-react"

import type { PositionValue, WizardStepKey } from "@/components/employees/wizard/schema"
import { Button } from "@/components/ui/button"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/** One review-step summary card with an edit jump. */
function ReviewRow({
  icon: Icon,
  title,
  lines,
  onEdit,
}: {
  icon: React.ComponentType<{ className?: string }>
  title: string
  lines: (string | null | undefined)[]
  onEdit: () => void
}) {
  const { t: tc } = useTranslation("common")
  const visible = lines.filter(Boolean) as string[]
  return (
    <div className="flex items-start gap-3 rounded-xl border p-3.5">
      <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent">
        <Icon className="size-4 text-muted-foreground" />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium">{title}</p>
        {visible.length > 0 ? (
          <ul className="mt-0.5 space-y-0.5">
            {visible.map((line, i) => (
              <li key={i} className="truncate text-xs text-muted-foreground">
                {line}
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-0.5 text-xs text-muted-foreground">—</p>
        )}
      </div>
      <Button
        type="button"
        variant="ghost"
        size="icon"
        className="size-8 shrink-0 text-muted-foreground"
        onClick={onEdit}
        aria-label={tc("actions.edit")}
      >
        <Pencil className="size-3.5" />
      </Button>
    </div>
  )
}

interface Props {
  fullName: string
  /** Spelled out rather than `Partial<EmployeeFormValues>`: `useWatch` yields a
   * deeply-partial shape, so nested rows are optional too. */
  watched: {
    phone?: string
    email?: string
    gender?: string
    birth_date?: string
    qualifications?: ({ education_level?: string; field_of_study?: string } | undefined)[]
  }
  watchedPositions: (Partial<PositionValue> | undefined)[]
  isTeacher: boolean
  teacherSubjectCount: number
  /** Whether saving will provision a portal account, per policy + the choice. */
  willCreateAccount: boolean
  draftCount: number
  onEdit: (step: WizardStepKey) => void
}

/**
 * The last stop before saving (create mode only): a read-only summary with a
 * jump back into any step. Rendered conditionally rather than hidden, so it
 * cannot be reached before the user has walked the wizard.
 */
export function ReviewStep({
  fullName,
  watched,
  watchedPositions,
  isTeacher,
  teacherSubjectCount,
  willCreateAccount,
  draftCount,
  onEdit,
}: Props) {
  const { t } = useTranslation("employees")

  return (
    <div className="space-y-3">
      <ReviewRow
        icon={UserRound}
        title={fullName || t("wizard.steps.identity")}
        onEdit={() => onEdit("identity")}
        lines={[
          watched.phone,
          watched.email,
          watched.gender ? t(`genders.${watched.gender}`) : null,
          watched.birth_date,
        ]}
      />
      <ReviewRow
        icon={Briefcase}
        title={t("wizard.steps.positions")}
        onEdit={() => onEdit("positions")}
        lines={watchedPositions
          .filter((p) => p?.job_title)
          .map(
            (p) =>
              `${t(`jobTitles.${p!.job_title}`)}${p!.is_primary ? ` · ${t("positions.primary")}` : ""}${p!.salary ? ` · ${Number(p!.salary).toLocaleString()} ETB` : ""}`
          )}
      />
      <div
        className={cn(
          "flex items-start gap-3 rounded-xl border p-3.5",
          willCreateAccount ? "border-primary/30 bg-primary/5" : "bg-muted/30"
        )}
      >
        <KeyRound
          className={cn(
            "mt-0.5 size-4.5 shrink-0",
            willCreateAccount ? "text-primary" : "text-muted-foreground"
          )}
        />
        <div className="min-w-0 text-sm">
          <p className="font-medium">
            {willCreateAccount ? t("account.willCreate") : t("account.willNotCreate")}
          </p>
          <p className="text-xs text-muted-foreground">
            {willCreateAccount ? t("account.willCreateHint") : t("account.willNotCreateHint")}
          </p>
        </div>
      </div>
      {isTeacher ? (
        <ReviewRow
          icon={BookOpen}
          title={t("wizard.steps.teaching")}
          onEdit={() => onEdit("teaching")}
          lines={[t("wizard.subjectCount", { count: String(teacherSubjectCount) })]}
        />
      ) : null}
      <ReviewRow
        icon={GraduationCap}
        title={t("wizard.steps.qualifications")}
        onEdit={() => onEdit("qualifications")}
        lines={(watched.qualifications ?? []).map((q) =>
          [
            q?.education_level ? t(`qualificationLevels.${q.education_level}`) : "",
            q?.field_of_study,
          ]
            .filter(Boolean)
            .join(" · ")
        )}
      />
      <ReviewRow
        icon={FileText}
        title={t("wizard.steps.documents")}
        onEdit={() => onEdit("documents")}
        lines={[t("wizard.documentCount", { count: String(draftCount) })]}
      />
    </div>
  )
}
