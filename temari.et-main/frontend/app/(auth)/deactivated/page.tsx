"use client"

import { ShieldAlert } from "lucide-react"
import Link from "next/link"
import { useSearchParams } from "next/navigation"
import { Suspense } from "react"

import { Button } from "@/components/ui/button"
import { useTranslation } from "@/lib/i18n"

function DeactivatedContent() {
  const { t } = useTranslation("users")
  const code = useSearchParams().get("code")
  const message =
    code === "account_banned" ? t("deactivated.bannedMessage") : t("deactivated.message")

  return (
    <div className="flex flex-col items-center gap-6 text-center">
      <div className="flex size-16 items-center justify-center rounded-2xl bg-destructive/10 text-destructive">
        <ShieldAlert className="size-8" />
      </div>
      <div className="space-y-2">
        <h1 className="text-2xl font-semibold tracking-tight">{t("deactivated.title")}</h1>
        <p className="text-muted-foreground text-sm leading-relaxed">{message}</p>
      </div>
      <Button asChild className="h-11 w-full">
        <Link href="/login">{t("deactivated.backToLogin")}</Link>
      </Button>
    </div>
  )
}

export default function DeactivatedPage() {
  return (
    <Suspense>
      <DeactivatedContent />
    </Suspense>
  )
}
