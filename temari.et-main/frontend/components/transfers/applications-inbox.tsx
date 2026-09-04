"use client"

import { CheckCircle2, XCircle } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Badge } from "@/components/ui/badge"
import { CopyableId } from "@/components/ui/copyable-id"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { Label } from "@/components/ui/label"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, GradeLevel, Paginated } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtDate } from "@/lib/dates"

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

const STATUS_TONE: Record<string, string> = {
  submitted: "border-warning/30 bg-warning/10 text-warning",
  accepted: "border-success/30 bg-success/10 text-success",
  declined: "border-destructive/30 bg-destructive/10 text-destructive",
  withdrawn: "border-border bg-muted text-muted-foreground",
}

export interface TransferApplicationRow {
  id: number
  student: {
    full_name: string | null
    public_id: string | null
    gender: string | null
    photo_url: string | null
  }
  from_school: string | null
  from_branch: string | null
  to_branch: string | null
  to_branch_id: number
  current_grade: string | null
  applicant_name: string | null
  reason: string
  status: string
  decline_note: string | null
  request_status: string | null
  created_at: string | null
}

/**
 * The DESTINATION school's inbox of family-initiated transfer applications:
 * limited student profile + reason; accepting places the student into a year
 * and grade and mints the standard transfer request the current school
 * decides.
 */
export function ApplicationsInbox() {
  const { t } = useTranslation("transfers")
  const { t: tc } = useTranslation("common")

  const [rows, setRows] = useState<TransferApplicationRow[] | null>(null)
  const [accepting, setAccepting] = useState<TransferApplicationRow | null>(null)
  const [declining, setDeclining] = useState<TransferApplicationRow | null>(null)
  const [declineNote, setDeclineNote] = useState("")
  const [years, setYears] = useState<AcademicYear[]>([])
  const [grades, setGrades] = useState<GradeLevel[]>([])
  const [yearId, setYearId] = useState("")
  const [gradeId, setGradeId] = useState("")
  const [working, setWorking] = useState(false)

  const load = useCallback(() => {
    setRows(null)
    let cancelled = false
    apiFetch<Paginated<TransferApplicationRow>>("/transfer-applications?per_page=100")
      .then((res) => !cancelled && setRows(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
  }, [tc])

  // eslint-disable-next-line react-hooks/set-state-in-effect -- load resets to loading state
  useEffect(() => load(), [load])

  useEffect(() => {
    if (!accepting) return
    // Placement grades follow the DESTINATION branch's grade offering.
    apiFetch<Paginated<GradeLevel>>(`/grade-levels?branch_id=${accepting.to_branch_id}`)
      .then((res) => setGrades(res.data))
      .catch(() => {})
    apiFetch<Paginated<AcademicYear>>("/academic-years?per_page=100")
      .then((res) => {
        // Placement must land at the application's destination branch.
        const usable = res.data.filter((year) => year.branch_id === accepting.to_branch_id)
        setYears(usable)
        setYearId(String(usable.find((y) => y.status === "active")?.id ?? usable[0]?.id ?? ""))
      })
      .catch(() => {})
  }, [accepting])

  async function accept() {
    if (!accepting || !yearId || !gradeId) return
    setWorking(true)
    try {
      await apiFetch(`/transfer-applications/${accepting.id}/accept`, {
        method: "POST",
        body: { to_academic_year_id: Number(yearId), to_grade_level_id: Number(gradeId) },
      })
      toast.success(t("applications.accepted"))
      setAccepting(null)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  async function decline() {
    if (!declining || !declineNote.trim()) return
    setWorking(true)
    try {
      await apiFetch(`/transfer-applications/${declining.id}/decline`, {
        method: "POST",
        body: { decline_note: declineNote.trim() },
      })
      toast.success(t("applications.declined"))
      setDeclining(null)
      setDeclineNote("")
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  const columns: DataTableColumn<TransferApplicationRow>[] = [
    {
      key: "student",
      label: t("columns.student"),
      primary: true,
      render: (row) => (
        <div className="flex min-w-0 items-center gap-2.5">
          <PersonAvatar name={row.student.full_name ?? "?"} photoUrl={row.student.photo_url} />
          <div className="min-w-0">
            <p className="truncate text-sm font-medium">{row.student.full_name}</p>
            <CopyableId value={row.student.public_id} />
          </div>
        </div>
      ),
      exportValue: (row) => row.student.full_name ?? "",
    },
    {
      key: "from",
      label: t("columns.from"),
      render: (row) => (
        <div className="text-xs">
          <p className="font-medium">{row.from_school}</p>
          <p className="text-muted-foreground">
            {row.from_branch}
            {row.current_grade ? ` · ${row.current_grade}` : ""}
          </p>
        </div>
      ),
      exportValue: (row) => `${row.from_school ?? ""} ${row.from_branch ?? ""}`,
    },
    {
      key: "applicant",
      label: t("applications.applicant"),
      mobileHidden: true,
      render: (row) => <span className="text-xs">{row.applicant_name ?? "—"}</span>,
      exportValue: (row) => row.applicant_name ?? "",
    },
    {
      key: "reason",
      label: t("form.reason"),
      mobileHidden: true,
      render: (row) => (
        <span className="line-clamp-2 max-w-64 text-xs text-muted-foreground">{row.reason}</span>
      ),
      exportValue: (row) => row.reason,
    },
    {
      key: "status",
      label: t("columns.status"),
      render: (row) => (
        <Badge variant="outline" className={cn("rounded-full", STATUS_TONE[row.status] ?? "")}>
          {t(`family.statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => row.status,
    },
    {
      key: "created_at",
      label: t("columns.requested"),
      mobileHidden: true,
      render: (row) => (
        <span className="text-xs text-muted-foreground">
          {row.created_at ? fmtDate(row.created_at) : "—"}
        </span>
      ),
      exportValue: (row) => row.created_at ?? "",
    },
  ]

  return (
    <>
      <DataTable
        columns={columns}
        data={rows ?? []}
        loading={rows === null}
        searchKeys={[]}
        emptyMessage={t("applications.empty")}
        exportFilename="transfer-applications"
        actions={[
          {
            label: t("applications.accept"),
            icon: CheckCircle2,
            primary: true,
            onClick: (row: TransferApplicationRow) => {
              setGradeId("")
              setAccepting(row)
            },
            hidden: (row: TransferApplicationRow) => row.status !== "submitted",
          },
          {
            label: t("applications.decline"),
            icon: XCircle,
            destructive: true,
            onClick: (row: TransferApplicationRow) => setDeclining(row),
            hidden: (row: TransferApplicationRow) => row.status !== "submitted",
          },
        ]}
      />

      {/* Accept: place into a year + grade → mints the standard request. */}
      <AlertDialog open={accepting !== null} onOpenChange={(open) => !open && setAccepting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("applications.acceptTitle")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("applications.acceptDesc", {
                student: accepting?.student.full_name ?? "",
                school: accepting?.from_school ?? "",
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("form.targetYear")}</Label>
              <Select value={yearId} onValueChange={setYearId}>
                <SelectTrigger className="w-full">
                  <SelectValue />
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
            <div className="space-y-2">
              <Label>{t("form.targetGrade")}</Label>
              <Select value={gradeId} onValueChange={setGradeId}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {grades.map((grade) => (
                    <SelectItem key={grade.id} value={String(grade.id)}>
                      {grade.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={working} disabled={!yearId || !gradeId}
              onClick={(e) => {
                e.preventDefault()
                accept()
              }}
            >
              {t("applications.accept")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Decline with a note the family will see. */}
      <AlertDialog open={declining !== null} onOpenChange={(open) => !open && setDeclining(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("applications.declineTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("applications.declineDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <textarea
            value={declineNote}
            onChange={(e) => setDeclineNote(e.target.value)}
            placeholder={t("applications.declinePlaceholder")}
            rows={3}
            className={TEXTAREA_CLASS}
          />
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={working} disabled={declineNote.trim().length === 0}
              onClick={(e) => {
                e.preventDefault()
                decline()
              }}
            >
              {t("applications.decline")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
