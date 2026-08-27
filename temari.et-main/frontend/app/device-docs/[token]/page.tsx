"use client"

import { ShieldX } from "lucide-react"
import { useParams } from "next/navigation"
import { useEffect, useState } from "react"

import { ApiDocsTab } from "@/components/devices/api-docs-tab"
import { Logo } from "@/components/ui/logo"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"

/**
 * PUBLIC copy of the device integration guide, behind a random share token
 * minted from the panel's Integration API tab. Same component, same content —
 * a firmware contractor reads exactly what platform staff read. The token
 * unlocks this page only (no data, no API); rotating or disabling it from
 * the panel kills the link instantly.
 */
export default function PublicDeviceDocsPage() {
  const { t } = useTranslation("devices")
  const params = useParams<{ token: string }>()

  const [state, setState] = useState<"loading" | "valid" | "invalid">("loading")

  useEffect(() => {
    let cancelled = false
    apiFetch(`/public/device-docs/${params.token}`, { anonymous: true })
      .then(() => !cancelled && setState("valid"))
      .catch(() => !cancelled && setState("invalid"))
    return () => {
      cancelled = true
    }
  }, [params.token])

  return (
    <div className="min-h-svh bg-muted/30 py-6 md:py-10">
      <div className="mx-auto w-full max-w-4xl space-y-6 px-4">
        <Logo tagline={t("api.title")} />

        {state === "loading" && <Skeleton className="h-96 w-full rounded-xl" />}

        {state === "invalid" && (
          <div className="rounded-xl border bg-card px-6 py-14 text-center">
            <ShieldX className="mx-auto size-10 text-destructive" />
            <h2 className="mt-4 text-base font-semibold">{t("api.share.invalidTitle")}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{t("api.share.invalidHint")}</p>
          </div>
        )}

        {state === "valid" && (
          <div className="[&_.page-gutter]:px-0">
            <ApiDocsTab />
          </div>
        )}
      </div>
    </div>
  )
}
