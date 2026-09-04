"use client"

import { useCallback } from "react"

import { AsyncCombobox, type AsyncComboboxOption } from "@/components/ui/async-combobox"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { InventoryItem, Paginated } from "@/lib/types"

/**
 * Server-searchable picker over the school's item master. Each option carries
 * the full item in `meta` so callers can show units and stock on hand.
 */
export function InventoryItemPicker({
  value,
  onChange,
  branchId,
  assetsOnly = false,
  disabled,
}: {
  value: InventoryItem | null
  onChange: (item: InventoryItem | null) => void
  /** Narrows the on-hand figure to one branch in school-wide workspaces. */
  branchId?: number | null
  /** Only is_asset items — the property register accepts nothing else. */
  assetsOnly?: boolean
  disabled?: boolean
}) {
  const { t } = useTranslation("inventory")

  const fetcher = useCallback(
    async (query: string, signal: AbortSignal): Promise<AsyncComboboxOption[]> => {
      const params = new URLSearchParams({ search: query, per_page: "25" })
      if (branchId != null) params.set("branch_id", String(branchId))
      if (assetsOnly) params.set("is_asset", "true")
      const res = await apiFetch<Paginated<InventoryItem>>(`/inventory/items?${params}`, { signal })
      return res.data.map((item) => ({
        value: String(item.id),
        label: item.name,
        description: [item.category_name, item.code].filter(Boolean).join(" · "),
        badge: `${Number(item.quantity_on_hand ?? 0)} ${t(`units.${item.unit}`)}`,
        meta: item,
      }))
    },
    [branchId, assetsOnly, t]
  )

  return (
    <AsyncCombobox
      value={value ? { value: String(value.id), label: value.name } : null}
      onChange={(option) => onChange((option?.meta as InventoryItem | undefined) ?? null)}
      fetcher={fetcher}
      minChars={1}
      placeholder={t("movement.itemPlaceholder")}
      searchPlaceholder={t("items.searchPlaceholder")}
      disabled={disabled}
    />
  )
}
