"use client"

import Link from "next/link"
import { ShieldAlert } from "lucide-react"

import { Button } from "@/components/ui/button"
import { useTranslation } from "@/lib/i18n"

export default function UnauthorizedPage() {
  const { t } = useTranslation("common")

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-6 px-4 text-center">
      <div className="bg-destructive/10 text-destructive flex size-16 items-center justify-center rounded-2xl">
        <ShieldAlert className="size-8" />
      </div>
      <div className="max-w-md space-y-2">
        <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">{t("unauthorized.title")}</h1>
        <p className="text-muted-foreground text-sm leading-relaxed">
          {t("unauthorized.message")}
        </p>
      </div>
      <Button asChild className="h-11">
        <Link href="/dashboard">{t("unauthorized.backToDashboard")}</Link>
      </Button>
    </div>
  )
}
