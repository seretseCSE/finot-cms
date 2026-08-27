"use client"

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { DOCUMENT_CATEGORIES } from "@/lib/document-categories"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/** Ethiopian document-type picker (birth certificate, ketebat, kebele ID…). */
export function DocumentCategorySelect({
  value,
  onChange,
  className,
}: {
  value: string | null | undefined
  onChange: (value: string) => void
  className?: string
}) {
  const { t } = useTranslation("students")

  return (
    <Select value={value ?? ""} onValueChange={onChange}>
      <SelectTrigger className={cn("h-9 w-full", className)}>
        <SelectValue placeholder={t("documents.categoryPlaceholder")} />
      </SelectTrigger>
      <SelectContent>
        {DOCUMENT_CATEGORIES.map((category) => (
          <SelectItem key={category} value={category}>
            {t(`documents.categories.${category}`)}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}

/** Tiny chip naming the document type on attachment rows. */
export function DocumentCategoryBadge({
  category,
  className,
}: {
  category: string | null | undefined
  className?: string
}) {
  const { t } = useTranslation("students")

  if (!category) return null

  return (
    <span
      className={cn(
        "inline-flex shrink-0 items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary",
        className,
      )}
    >
      {t(`documents.categories.${category}`)}
    </span>
  )
}
