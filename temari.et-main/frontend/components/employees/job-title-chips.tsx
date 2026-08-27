"use client"

import { Lock } from "lucide-react"

import { JOB_TITLES } from "@/lib/data"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/** Job titles whose kernel role requires an account — locked ON everywhere. */
export const ROLE_MAPPED_JOB_TITLES = ["director", "teacher", "registrar", "finance_officer", "storekeeper"]

/**
 * Toggle-chip picker for the staff portal-account policy: which job titles
 * come with a login at hire. The five role-mapped titles are locked on —
 * their branch memberships cannot exist without a user account.
 */
export function JobTitleChips({
  value,
  onChange,
  disabled,
}: {
  value: string[]
  onChange: (titles: string[]) => void
  disabled?: boolean
}) {
  const { t } = useTranslation("employees")

  function toggle(code: string) {
    onChange(value.includes(code) ? value.filter((v) => v !== code) : [...value, code])
  }

  return (
    <div className="flex flex-wrap gap-1.5">
      {JOB_TITLES.map((code) => {
        const locked = ROLE_MAPPED_JOB_TITLES.includes(code)
        const selected = locked || value.includes(code)
        return (
          <button
            key={code}
            type="button"
            disabled={disabled || locked}
            onClick={() => toggle(code)}
            aria-pressed={selected}
            className={cn(
              "pressable inline-flex min-h-8 items-center gap-1 rounded-full border px-3 text-xs font-medium transition-colors",
              selected
                ? "border-primary/40 bg-primary/10 text-primary"
                : "text-muted-foreground hover:bg-muted",
              locked && "opacity-80",
              disabled && !locked && "pointer-events-none opacity-60",
            )}
          >
            {locked ? <Lock className="size-3" /> : null}
            {t(`jobTitles.${code}`)}
          </button>
        )
      })}
    </div>
  )
}
