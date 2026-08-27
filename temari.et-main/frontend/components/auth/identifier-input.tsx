"use client"

import { IdCard, Smartphone } from "lucide-react"

import { Input } from "@/components/ui/input"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"
import { looksLikePublicId } from "@/lib/validators"

interface Props {
  value: string
  onChange: (value: string) => void
  onBlur?: () => void
  id?: string
  name?: string
  placeholder?: string
  autoFocus?: boolean
  className?: string
  "aria-describedby"?: string
  "aria-invalid"?: boolean | "true" | "false"
  "aria-label"?: string
}

/**
 * The one smart credential field: a phone number OR a Temari student ID.
 * The mode is inferred while typing — any letter flips to ID mode (uppercase,
 * mono, 6 chars) with a helper chip so a student knows they're on the right
 * track. Keyboard stays full (`inputMode="text"`): a numeric keypad would
 * lock ID-login students out of typing letters at all.
 */
export function IdentifierInput({
  value,
  onChange,
  onBlur,
  id,
  name,
  placeholder,
  autoFocus,
  className,
  ...aria
}: Props) {
  const { t } = useTranslation("auth")
  const idMode = looksLikePublicId(value)
  const Icon = idMode ? IdCard : Smartphone

  return (
    <div className="space-y-1.5">
      <div className="relative">
        <Icon className="text-muted-foreground pointer-events-none absolute left-4 top-1/2 size-4.5 -translate-y-1/2" />
        <Input
          type="text"
          inputMode="text"
          autoComplete="username"
          autoCapitalize="characters"
          autoCorrect="off"
          spellCheck={false}
          maxLength={idMode ? 6 : 20}
          id={id}
          name={name}
          {...aria}
          autoFocus={autoFocus}
          placeholder={placeholder ?? t("login.identifierPlaceholder")}
          value={value}
          onChange={(e) => {
            const next = e.target.value
            onChange(looksLikePublicId(next) ? next.toUpperCase() : next)
          }}
          onBlur={onBlur}
          className={cn(
            "bg-muted/60 h-13 rounded-xl border-0 pl-11 pr-4 text-base focus-visible:ring-2 focus-visible:ring-primary/30",
            idMode && "font-mono uppercase tracking-[0.25em]",
            className,
          )}
        />
      </div>
      {idMode ? (
        <p className="text-primary flex items-center gap-1.5 text-xs font-medium">
          <IdCard className="size-3.5 shrink-0" />
          {t("login.idModeHint")}
        </p>
      ) : null}
    </div>
  )
}
