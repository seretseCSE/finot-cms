"use client"

import {
  BookOpenCheck,
  CheckCheck,
  ClipboardCheck,
  Presentation,
  Send,
  Trash2,
} from "lucide-react"
import { useEffect, useMemo, useState } from "react"
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
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { fmtDate } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type { EvaluationScoreLine, TeacherEvaluationDetail } from "@/lib/types"
import { cn } from "@/lib/utils"

export const EVALUATION_STATUS_BADGE: Record<string, string> = {
  draft: "bg-muted text-muted-foreground",
  submitted: "border-warning/30 bg-warning/10 text-warning",
  acknowledged: "border-success/30 bg-success/10 text-success",
}

/** Overall (0–100) → the traffic tone directors expect on appraisal sheets. */
export function scoreTone(score: number | null): string {
  if (score === null) return "text-muted-foreground"
  if (score >= 75) return "text-success"
  if (score >= 50) return "text-warning"
  return "text-destructive"
}

/**
 * The appraisal sheet: evaluator mode scores each rubric line (tap ratings —
 * thumb-sized, the phone IS the clipboard), writes narratives and shares;
 * the evaluated teacher gets the same sheet read-only plus the acknowledge
 * signature box. One component, both sides of the ritual.
 */
export function EvaluationEditor({
  evaluationId,
  canManage,
  open,
  onOpenChange,
  onChanged,
}: {
  evaluationId: number | null
  canManage: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: () => void
}) {
  const { t } = useTranslation("hr")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [detail, setDetail] = useState<TeacherEvaluationDetail | null>(null)
  const [scores, setScores] = useState<EvaluationScoreLine[]>([])
  const [strengths, setStrengths] = useState("")
  const [improvements, setImprovements] = useState("")
  const [comment, setComment] = useState("")
  const [saving, setSaving] = useState(false)
  const [sharing, setSharing] = useState(false)
  const [shareOpen, setShareOpen] = useState(false)
  const [acknowledging, setAcknowledging] = useState(false)

  const editable = canManage && detail?.status === "draft"

  useEffect(() => {
    if (!open || evaluationId === null) return
    let cancelled = false
    /* eslint-disable react-hooks/set-state-in-effect -- reset for the new record */
    setDetail(null)
    setComment("")
    /* eslint-enable react-hooks/set-state-in-effect */
    apiFetch<{ data: TeacherEvaluationDetail }>(`/hr/evaluations/${evaluationId}`)
      .then((res) => {
        if (cancelled) return
        setDetail(res.data)
        setScores(res.data.scores)
        setStrengths(res.data.strengths ?? "")
        setImprovements(res.data.improvements ?? "")
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        onOpenChange(false)
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc/onOpenChange stable enough
  }, [open, evaluationId])

  // Live overall preview while scoring (mirrors the server formula).
  const liveOverall = useMemo(() => {
    const scored = scores.filter((s) => s.score !== null)
    if (scored.length === 0) return null
    return (
      Math.round(
        scored.reduce(
          (sum, s) => sum + (s.max_score > 0 ? ((s.score ?? 0) / s.max_score) * s.weight : 0),
          0,
        ) * 100,
      ) / 100
    )
  }, [scores])

  const allScored = scores.length > 0 && scores.every((s) => s.score !== null)

  async function save(silent = false): Promise<boolean> {
    if (!detail) return false
    setSaving(true)
    try {
      await apiFetch(`/hr/evaluations/${detail.id}`, {
        method: "PUT",
        body: {
          strengths: strengths || null,
          improvements: improvements || null,
          scores: scores.map((s) => ({ id: s.id, score: s.score, note: s.note })),
        },
      })
      if (!silent) toast.success(tc("actions.saved"))
      onChanged()
      return true
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      return false
    } finally {
      setSaving(false)
    }
  }

  async function share() {
    if (!detail) return
    setSharing(true)
    try {
      if (!(await save(true))) return
      await apiFetch(`/hr/evaluations/${detail.id}/submit`, { method: "POST" })
      toast.success(t("evaluations.shared"))
      setShareOpen(false)
      onOpenChange(false)
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSharing(false)
    }
  }

  async function acknowledge() {
    if (!detail) return
    setAcknowledging(true)
    try {
      await apiFetch(`/hr/evaluations/${detail.id}/acknowledge`, {
        method: "POST",
        body: { teacher_comment: comment || null },
      })
      toast.success(t("evaluations.acknowledged"))
      onOpenChange(false)
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setAcknowledging(false)
    }
  }

  function setScore(id: number, score: number | null) {
    setScores((current) => current.map((s) => (s.id === id ? { ...s, score } : s)))
  }

  const signals = detail?.signals
  const signalTiles =
    signals && (signals.classes > 0 || signals.lesson_plans_total > 0)
      ? [
          {
            key: "marklists",
            icon: BookOpenCheck,
            value: `${signals.marklists_approved}/${signals.classes}`,
            label: t("evaluations.signalMarklists"),
          },
          {
            key: "plans",
            icon: Presentation,
            value: `${signals.lesson_plans_approved}/${signals.lesson_plans_total}`,
            label: t("evaluations.signalPlans"),
          },
        ]
      : []

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-2xl">
        {confirmDialog}
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle className="flex items-center gap-2">
            <ClipboardCheck className="size-4" />
            {t("evaluations.sheetTitle")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>

        <ResponsiveSheetBody className="space-y-5">
          {detail === null ? (
            <>
              <Skeleton className="h-20 rounded-2xl" />
              <Skeleton className="h-64 rounded-2xl" />
            </>
          ) : (
            <>
              {/* Who + when + the live score */}
              <div className="flex items-center gap-3 rounded-2xl border p-3.5">
                <PersonAvatar
                  name={detail.employee.name ?? "?"}
                  photoUrl={detail.employee.photo_url}
                  className="size-11"
                />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold">{detail.employee.name}</p>
                  <p className="text-muted-foreground text-xs">
                    {detail.term.name}
                    {detail.evaluator_name ? ` · ${detail.evaluator_name}` : ""}
                  </p>
                  <Badge
                    variant="outline"
                    className={cn("mt-1 border", EVALUATION_STATUS_BADGE[detail.status])}
                  >
                    {t(`evaluations.statuses.${detail.status}`)}
                  </Badge>
                </div>
                <div className="text-right">
                  <p className={cn("font-display text-2xl font-bold tabular-nums", scoreTone(liveOverall))}>
                    {liveOverall ?? "—"}
                  </p>
                  <p className="text-muted-foreground text-[10px] uppercase tracking-wide">
                    {t("evaluations.outOf100")}
                  </p>
                </div>
              </div>

              {/* Platform evidence — facts for the evaluator, never a score. */}
              {signalTiles.length > 0 && (
                <div className="grid grid-cols-2 gap-2">
                  {signalTiles.map(({ key, icon: Icon, value, label }) => (
                    <div key={key} className="bg-muted/40 flex items-center gap-2.5 rounded-xl px-3 py-2.5">
                      <Icon className="text-muted-foreground size-4 shrink-0" />
                      <div className="min-w-0">
                        <p className="text-sm font-semibold tabular-nums">{value}</p>
                        <p className="text-muted-foreground truncate text-[11px]">{label}</p>
                      </div>
                    </div>
                  ))}
                </div>
              )}

              {/* The rubric */}
              <div className="space-y-3">
                {scores.map((line) => (
                  <div key={line.id} className="rounded-2xl border p-3.5">
                    <div className="flex items-start justify-between gap-2">
                      <p className="text-sm font-medium">{line.label}</p>
                      <Badge variant="secondary" className="shrink-0 tabular-nums">
                        {line.weight}%
                      </Badge>
                    </div>
                    {editable ? (
                      <div className="mt-2.5 flex flex-wrap gap-1.5">
                        {Array.from({ length: Math.round(line.max_score) }, (_, i) => i + 1).map(
                          (value) => (
                            <button
                              key={value}
                              type="button"
                              onClick={() => setScore(line.id, line.score === value ? null : value)}
                              aria-pressed={line.score === value}
                              className={cn(
                                "size-11 rounded-xl border text-sm font-semibold tabular-nums transition-colors",
                                line.score !== null && value <= line.score
                                  ? "border-primary bg-primary text-primary-foreground"
                                  : "hover:bg-accent/60",
                              )}
                            >
                              {value}
                            </button>
                          ),
                        )}
                      </div>
                    ) : (
                      <p className={cn("mt-1.5 text-sm font-semibold tabular-nums", scoreTone(
                        line.score !== null ? (line.score / line.max_score) * 100 : null,
                      ))}>
                        {line.score ?? "—"} / {line.max_score}
                      </p>
                    )}
                    {editable ? (
                      <Input
                        value={line.note ?? ""}
                        onChange={(event) =>
                          setScores((current) =>
                            current.map((s) =>
                              s.id === line.id ? { ...s, note: event.target.value || null } : s,
                            ),
                          )
                        }
                        placeholder={t("evaluations.notePlaceholder")}
                        className="mt-2.5 h-9 text-xs"
                      />
                    ) : line.note ? (
                      <p className="text-muted-foreground mt-1.5 text-xs">{line.note}</p>
                    ) : null}
                  </div>
                ))}
              </div>

              {/* Narratives */}
              {editable ? (
                <div className="space-y-4">
                  <div className="space-y-1.5">
                    <p className="text-sm font-medium">{t("evaluations.strengths")}</p>
                    <Textarea
                      value={strengths}
                      onChange={(event) => setStrengths(event.target.value)}
                      rows={2}
                      placeholder={t("evaluations.strengthsPlaceholder")}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <p className="text-sm font-medium">{t("evaluations.improvements")}</p>
                    <Textarea
                      value={improvements}
                      onChange={(event) => setImprovements(event.target.value)}
                      rows={2}
                      placeholder={t("evaluations.improvementsPlaceholder")}
                    />
                  </div>
                </div>
              ) : (
                <div className="space-y-3">
                  {detail.strengths && (
                    <div className="rounded-2xl border p-3.5">
                      <p className="text-success text-xs font-semibold uppercase tracking-wide">
                        {t("evaluations.strengths")}
                      </p>
                      <p className="mt-1 text-sm">{detail.strengths}</p>
                    </div>
                  )}
                  {detail.improvements && (
                    <div className="rounded-2xl border p-3.5">
                      <p className="text-warning text-xs font-semibold uppercase tracking-wide">
                        {t("evaluations.improvements")}
                      </p>
                      <p className="mt-1 text-sm">{detail.improvements}</p>
                    </div>
                  )}
                </div>
              )}

              {/* The teacher's signature line */}
              {detail.status === "acknowledged" && (
                <div className="border-success/30 bg-success/5 rounded-2xl border p-3.5">
                  <p className="text-success flex items-center gap-1.5 text-xs font-semibold">
                    <CheckCheck className="size-3.5" />
                    {t("evaluations.acknowledgedOn", {
                      date: detail.acknowledged_at ? fmtDate(detail.acknowledged_at) : "",
                    })}
                  </p>
                  {detail.teacher_comment && (
                    <p className="text-muted-foreground mt-1 text-sm">“{detail.teacher_comment}”</p>
                  )}
                </div>
              )}
              {!canManage && detail.status === "submitted" && (
                <div className="space-y-2 rounded-2xl border p-3.5">
                  <p className="text-sm font-medium">{t("evaluations.acknowledgePrompt")}</p>
                  <Textarea
                    value={comment}
                    onChange={(event) => setComment(event.target.value)}
                    rows={2}
                    placeholder={t("evaluations.commentPlaceholder")}
                  />
                </div>
              )}
            </>
          )}
        </ResponsiveSheetBody>

        {detail !== null && (
          <ResponsiveSheetFooter>
            {editable ? (
              <>
                <Button
                  variant="outline"
                  size="icon"
                  className="size-11 shrink-0"
                  disabled={saving || sharing}
                  onClick={() =>
                    confirmDelete(async () => {
                      await apiFetch(`/hr/evaluations/${detail.id}`, { method: "DELETE" })
                      toast.success(tc("actions.deleted"))
                      onOpenChange(false)
                      onChanged()
                    })
                  }
                  aria-label={tc("actions.delete")}
                  title={tc("actions.delete")}
                >
                  <Trash2 className="size-4" />
                </Button>
                <Button
                  variant="outline"
                  className="h-11 flex-1"
                  onClick={() => void save()}
                  loading={saving}
                  disabled={sharing}
                >
                  {t("evaluations.saveDraft")}
                </Button>
                <Button
                  className="h-11 flex-1"
                  onClick={() => setShareOpen(true)}
                  disabled={!allScored || saving}
                  loading={sharing}
                >
                  <Send className="size-4" />
                  {t("evaluations.share")}
                </Button>
              </>
            ) : !canManage && detail.status === "submitted" ? (
              <Button className="h-11 flex-1" onClick={() => void acknowledge()} loading={acknowledging}>
                <CheckCheck className="size-4" />
                {t("evaluations.acknowledge")}
              </Button>
            ) : (
              <Button variant="outline" className="h-11 flex-1" onClick={() => onOpenChange(false)}>
                {tc("actions.close")}
              </Button>
            )}
          </ResponsiveSheetFooter>
        )}

        {/* Sharing puts the record in front of the teacher — pause first. */}
        <AlertDialog open={shareOpen} onOpenChange={setShareOpen}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t("evaluations.shareTitle")}</AlertDialogTitle>
              <AlertDialogDescription>
                {t("evaluations.shareBody", { name: detail?.employee.name ?? "" })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel disabled={sharing}>{tc("actions.cancel")}</AlertDialogCancel>
              <AlertDialogAction
                loading={sharing}
                onClick={(event) => {
                  event.preventDefault()
                  void share()
                }}
              >
                {t("evaluations.shareConfirm")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
