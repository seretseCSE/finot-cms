"use client"

import { Check, ChevronsUpDown } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { ContextOption } from "@/lib/types"
import { cn } from "@/lib/utils"

export function ContextSwitcher() {
  const { options, activeOption, switchTo } = useSchoolContext()
  const { t } = useTranslation("common")

  function label(option: ContextOption): string {
    if (option.schoolId === null) return t("context.platform")
    if (option.branchId === null) return `${option.schoolName} · ${t("context.allBranches")}`
    return `${option.schoolName} · ${option.branchName}`
  }

  if (options.length === 0) return null

  if (options.length === 1) {
    return <span className="text-muted-foreground truncate text-sm">{label(options[0])}</span>
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" className="max-w-[240px] justify-between gap-2">
          <span className="truncate">
            {activeOption ? label(activeOption) : t("selectContext")}
          </span>
          <ChevronsUpDown className="size-4 shrink-0 opacity-60" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-72">
        <DropdownMenuLabel>{t("switchContext")}</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {options.map((option) => (
          <DropdownMenuItem
            key={option.id}
            onClick={() => switchTo(option)}
            className="justify-between gap-2"
          >
            <span className="truncate">{label(option)}</span>
            <Check
              className={cn(
                "size-4 shrink-0",
                activeOption?.id === option.id ? "opacity-100" : "opacity-0",
              )}
            />
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
