"use client"

import { useMemo, useState } from "react"

import { ApiDocsTab } from "@/components/devices/api-docs-tab"
import { CardsTab } from "@/components/devices/cards-tab"
import { DevicesTab } from "@/components/devices/devices-tab"
import { MessagesTab } from "@/components/devices/messages-tab"
import { RequestsTab } from "@/components/devices/requests-tab"
import { ScanLogTab } from "@/components/devices/scan-log-tab"
import { PageHeader } from "@/components/ui/page-header"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

type Tab = "devices" | "cards" | "requests" | "scans" | "messages" | "api"

/**
 * The attendance-hardware hub: RFID terminals, the MIFARE card register, the
 * raw scan log and the guardian-alert ledger — one page, four tabs, so a
 * registrar can go from "issue a card" to "did the gate see it" to "was the
 * parent texted" without changing rooms.
 */
export default function DevicesPage() {
  const { t } = useTranslation("devices")
  const permissions = useEffectivePermissions()

  const tabs = useMemo(() => {
    const list: Tab[] = []
    if (permissions.includes("devices.view")) list.push("devices")
    if (permissions.includes("cards.view")) list.push("cards")
    if (permissions.includes("cards.report") || permissions.includes("cards.manage")) {
      list.push("requests")
    }
    if (permissions.includes("devices.view")) list.push("scans")
    if (permissions.includes("attendance.view")) list.push("messages")
    // The integration handbook — hardware is platform territory (devices.manage).
    if (permissions.includes("devices.manage")) list.push("api")
    return list
  }, [permissions])

  const [tab, setTab] = useState<Tab | null>(null)
  const active = tab && tabs.includes(tab) ? tab : tabs[0]

  return (
    <div className="space-y-6">
      <PageHeader title={t("title")} description={t("subtitle")} />

      <div className="page-gutter">
        <div className="scrollbar-none -mx-1 flex gap-1.5 overflow-x-auto px-1">
          {tabs.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => setTab(key)}
              className={cn(
                "pressable inline-flex min-h-9 shrink-0 items-center rounded-full border px-3.5 text-xs font-medium transition-colors",
                active === key
                  ? "border-primary/40 bg-primary/10 text-primary"
                  : "text-muted-foreground hover:bg-muted",
              )}
              aria-pressed={active === key}
            >
              {t(`tabs.${key}`)}
            </button>
          ))}
        </div>
      </div>

      {active === "devices" && <DevicesTab />}
      {active === "cards" && <CardsTab />}
      {active === "requests" && <RequestsTab />}
      {active === "scans" && <ScanLogTab />}
      {active === "messages" && <MessagesTab />}
      {active === "api" && <ApiDocsTab manageShare />}
    </div>
  )
}
