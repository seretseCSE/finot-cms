"use client"

import { Plus, Trash2 } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { InventoryCategory } from "@/lib/types"

/**
 * Manage item categories: platform rows are read-only context, the school's
 * own rows can be added, toggled and (while unused) deleted.
 */
export function InventoryCategoriesDialog({
  categories,
  open,
  onOpenChange,
  onChanged,
}: {
  categories: InventoryCategory[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [name, setName] = useState("")
  const [adding, setAdding] = useState(false)

  async function add() {
    const trimmed = name.trim()
    if (!trimmed) return
    setAdding(true)
    try {
      await apiFetch("/inventory/categories", { method: "POST", body: { name: trimmed } })
      setName("")
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setAdding(false)
    }
  }

  async function toggle(category: InventoryCategory) {
    try {
      await apiFetch(`/inventory/categories/${category.id}`, {
        method: "PUT",
        body: { is_active: !category.is_active },
      })
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  function remove(category: InventoryCategory) {
    confirmDelete(async () => {
      try {
        await apiFetch(`/inventory/categories/${category.id}`, { method: "DELETE" })
        onChanged()
      } catch (error) {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      }
    }, t("categories.deleteBody"))
  }

  const platform = categories.filter((c) => c.is_platform)
  const own = categories.filter((c) => !c.is_platform)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("categories.title")}</DialogTitle>
          <DialogDescription>{t("categories.subtitle")}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <Input
              placeholder={t("categories.name")}
              value={name}
              onChange={(e) => setName(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  e.preventDefault()
                  void add()
                }
              }}
            />
            <Button type="button" loading={adding} disabled={!name.trim()} onClick={add}>
              <Plus className="size-4" />
              {t("categories.add")}
            </Button>
          </div>

          {own.length > 0 && (
            <div className="space-y-1.5">
              <p className="text-xs font-medium text-muted-foreground">{t("categories.yours")}</p>
              {own.map((category) => (
                <div
                  key={category.id}
                  className="flex items-center justify-between gap-2 rounded-xl border px-3 py-2"
                >
                  <span className="min-w-0 truncate text-sm">{category.name}</span>
                  <span className="flex shrink-0 items-center gap-1.5">
                    <Switch
                      checked={category.is_active}
                      onCheckedChange={() => toggle(category)}
                      aria-label={t("items.isActiveLabel")}
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="size-8 text-destructive"
                      onClick={() => remove(category)}
                      aria-label={tc("actions.delete")}
                      title={tc("actions.delete")}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </span>
                </div>
              ))}
            </div>
          )}

          <div className="space-y-1.5">
            <p className="text-xs font-medium text-muted-foreground">
              {t("categories.platform")}
            </p>
            <div className="flex flex-wrap gap-1.5">
              {platform.map((category) => (
                <Badge key={category.id} variant="outline" className="rounded-full font-normal">
                  {category.name}
                </Badge>
              ))}
            </div>
          </div>
        </div>

        {confirmDialog}
      </DialogContent>
    </Dialog>
  )
}
