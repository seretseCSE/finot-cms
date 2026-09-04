"use client"

import { useEffect, useState } from "react"
import { toast } from "sonner"

import { HolderPicker } from "@/components/inventory/asset-sheets"
import { InventoryItemPicker } from "@/components/inventory/item-picker"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, AssetHolderOption, InventoryItem } from "@/lib/types"

/**
 * Bulk-issue one book to a whole section: every active student gets a copy,
 * current holders are skipped, one aggregate ledger movement carries it.
 */
export function IssueTextbooksSheet({
  open,
  onOpenChange,
  onSaved,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()

  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [years, setYears] = useState<AcademicYear[]>([])
  const [yearId, setYearId] = useState("")
  const [book, setBook] = useState<InventoryItem | null>(null)
  const [section, setSection] = useState<AssetHolderOption | null>(null)
  const [perStudent, setPerStudent] = useState("1")
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setBook(null)
    setSection(null)
    setPerStudent("1")
    setBranchId(null)
    setBranchError(null)
  }, [open])

  // Academic years follow the target branch (years are branch-scoped). The
  // no-branch reset happens inside the microtask so the effect body itself
  // never sets state synchronously.
  useEffect(() => {
    if (!open) return
    let cancelled = false

    if (needsBranch && branchId == null) {
      queueMicrotask(() => {
        if (cancelled) return
        setYears([])
        setYearId("")
      })
    } else {
      const params = branchId != null ? `?branch_id=${branchId}` : ""
      apiFetch<{ data: AcademicYear[] }>(`/academic-years${params}`)
        .then((res) => {
          if (cancelled) return
          setYears(res.data)
          const active = res.data.find((y) => y.status === "active")
          setYearId(active ? String(active.id) : "")
        })
        .catch(() => {})
    }

    return () => {
      cancelled = true
    }
  }, [open, needsBranch, branchId])

  async function submit() {
    if (needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }
    setBusy(true)
    try {
      const res = await apiFetch<{ data: { issued: number; skipped: number } }>(
        "/inventory/textbooks/issue",
        {
          method: "POST",
          body: {
            ...(branchId != null ? { branch_id: branchId } : {}),
            academic_year_id: yearId ? Number(yearId) : null,
            inventory_item_id: book?.id ?? null,
            section_id: section?.id ?? null,
            quantity_per_student: Number(perStudent) || 1,
          },
        }
      )
      toast.success(
        t("textbooks.issuedToast")
          .replace("{issued}", String(res.data.issued))
          .replace("{skipped}", String(res.data.skipped))
      )
      onSaved()
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-lg">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("textbooks.issueTitle")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          <p className="rounded-xl bg-muted p-3 text-xs text-muted-foreground">
            {t("textbooks.issueHelp")}
          </p>

          {needsBranch && (
            <BranchField
              value={branchId}
              onChange={(id) => {
                setBranchId(id)
                setBranchError(null)
                setSection(null)
              }}
              error={branchError}
            />
          )}

          <div className="space-y-1.5">
            <Label>{t("textbooks.yearLabel")}</Label>
            <Select value={yearId} onValueChange={setYearId}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t("textbooks.yearLabel")} />
              </SelectTrigger>
              <SelectContent>
                {years.map((year) => (
                  <SelectItem key={year.id} value={String(year.id)}>
                    {year.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label>{t("textbooks.bookLabel")}</Label>
            <InventoryItemPicker value={book} onChange={setBook} branchId={branchId} />
          </div>

          <div className="space-y-1.5">
            <Label>{t("textbooks.sectionLabel")}</Label>
            <HolderPicker
              type="section"
              branchId={branchId}
              value={section}
              onChange={setSection}
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="tb-per">{t("textbooks.perStudentLabel")}</Label>
            <Input
              id="tb-per"
              type="number"
              inputMode="numeric"
              min={1}
              max={5}
              className="no-spinner w-24"
              value={perStudent}
              onChange={(e) => setPerStudent(e.target.value)}
            />
          </div>
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            type="button"
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
            disabled={busy}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            type="button"
            className="h-11 flex-1"
            loading={busy}
            disabled={!book || !section || !yearId}
            onClick={submit}
          >
            {t("textbooks.issue")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
