"use client"

import {
  Check,
  ChevronLeft,
  ChevronRight,
  CloudOff,
  Flag,
  LayoutGrid,
  X,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { Fragment, useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { PartBanner, PassageCard, QuestionAttachments, QuestionStem } from "@/components/lms/question-content"
import { ResultView } from "@/components/lms/result-view"
import { romanNumeral } from "@/components/lms/shared"
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
import { Button } from "@/components/ui/button"
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from "@/components/ui/drawer"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"
import type { AttemptState, PlayerQuestion } from "@/lib/types"

/**
 * The distraction-free exam player (ADR-016). Everything that matters is
 * server-side; this component renders, autosaves with a retry queue (3G
 * reality), ticks the local clock against the server deadline, and sends
 * integrity beacons (blur/paste/fullscreen-exit) that surface as review
 * flags — never auto-fails.
 */
export function ExamPlayer({ attemptId }: { attemptId: number }) {
  const { t } = useTranslation("lms")
  const router = useRouter()

  const [state, setState] = useState<AttemptState | null>(null)
  const [failed, setFailed] = useState(false)
  const [index, setIndex] = useState(0)
  const [answers, setAnswers] = useState<Record<number, unknown>>({})
  const [saveState, setSaveState] = useState<"idle" | "saving" | "saved" | "offline">("idle")
  const [remaining, setRemaining] = useState<number | null>(null)
  const [paletteOpen, setPaletteOpen] = useState(false)
  const [confirmSubmit, setConfirmSubmit] = useState(false)
  const [finished, setFinished] = useState(false)

  const pendingRef = useRef<Map<number, unknown>>(new Map())
  const timersRef = useRef<Map<number, ReturnType<typeof setTimeout>>>(new Map())
  const submittingRef = useRef(false)

  // ── load the paper ──
  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: AttemptState }>(`/me/exam-attempts/${attemptId}`)
      .then((res) => {
        if (cancelled) return
        setState(res.data)
        setRemaining(res.data.remaining_seconds)
        if (res.data.status !== "in_progress") {
          setFinished(true)
          return
        }
        const saved: Record<number, unknown> = {}
        for (const question of res.data.questions ?? []) {
          if (question.answer !== null && question.answer !== undefined) {
            saved[question.question_id] = question.answer
          }
        }
        setAnswers(saved)
        const firstUnanswered = (res.data.questions ?? []).findIndex(
          (question) => saved[question.question_id] === undefined,
        )
        setIndex(firstUnanswered === -1 ? 0 : firstUnanswered)
      })
      .catch(() => !cancelled && setFailed(true))
    return () => {
      cancelled = true
    }
  }, [attemptId])

  // ── the clock (local tick against the server deadline) ──
  useEffect(() => {
    if (remaining === null || finished) return
    if (remaining <= 0) {
      void submit(true)
      return
    }
    const timer = setTimeout(() => setRemaining((prev) => (prev === null ? null : prev - 1)), 1000)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tick
  }, [remaining, finished])

  // ── integrity beacons (fire-and-forget; flags, not punishments) ──
  const beacon = useCallback(
    (type: string) => {
      void apiFetch(`/me/exam-attempts/${attemptId}/events`, {
        method: "POST",
        body: { type },
      }).catch(() => {})
    },
    [attemptId],
  )

  useEffect(() => {
    if (finished || state === null) return
    const onBlur = () => beacon("blur")
    const onFocus = () => beacon("focus")
    const onPaste = () => beacon("paste")
    const onCopy = () => beacon("copy")
    window.addEventListener("blur", onBlur)
    window.addEventListener("focus", onFocus)
    document.addEventListener("paste", onPaste)
    document.addEventListener("copy", onCopy)
    return () => {
      window.removeEventListener("blur", onBlur)
      window.removeEventListener("focus", onFocus)
      document.removeEventListener("paste", onPaste)
      document.removeEventListener("copy", onCopy)
    }
  }, [beacon, finished, state])

  // ── autosave with retry (the 3G contract: nothing is ever lost) ──
  const flush = useCallback(
    async (questionId: number) => {
      const value = pendingRef.current.get(questionId)
      if (value === undefined) return
      setSaveState("saving")
      try {
        await apiFetch(`/me/exam-attempts/${attemptId}/answer`, {
          method: "POST",
          body: { question_id: questionId, answer: value },
        })
        pendingRef.current.delete(questionId)
        setSaveState(pendingRef.current.size > 0 ? "saving" : "saved")
      } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
          // Deadline passed server-side — the attempt was swept; show results.
          pendingRef.current.delete(questionId)
          setFinished(true)
          return
        }
        setSaveState("offline")
        const timer = setTimeout(() => void flush(questionId), 5000)
        timersRef.current.set(questionId, timer)
      }
    },
    [attemptId],
  )

  const setAnswer = useCallback(
    (questionId: number, value: unknown) => {
      setAnswers((prev) => ({ ...prev, [questionId]: value }))
      pendingRef.current.set(questionId, value)
      const existing = timersRef.current.get(questionId)
      if (existing) clearTimeout(existing)
      timersRef.current.set(
        questionId,
        setTimeout(() => void flush(questionId), 600),
      )
    },
    [flush],
  )

  useEffect(() => {
    const timers = timersRef.current
    return () => {
      timers.forEach((timer) => clearTimeout(timer))
    }
  }, [])

  async function submit(auto = false) {
    if (submittingRef.current) return
    submittingRef.current = true
    try {
      // Flush any pending answers first, best effort.
      await Promise.all([...pendingRef.current.keys()].map((questionId) => flush(questionId)))
      await apiFetch(`/me/exam-attempts/${attemptId}/submit`, { method: "POST", body: {} })
      if (auto) toast.info(t("player.autoSubmitted"))
      setFinished(true)
    } catch (error) {
      submittingRef.current = false
      toast.error(error instanceof ApiError ? error.message : t("player.loadFailed"))
    }
  }

  const questions = useMemo(() => state?.questions ?? [], [state])
  const answeredCount = questions.filter((q) => {
    const value = answers[q.question_id]
    return value !== undefined && value !== null && value !== ""
  }).length

  if (failed) {
    return (
      <div className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-background p-6">
        <p className="text-sm text-muted-foreground">{t("player.loadFailed")}</p>
        <Button variant="outline" onClick={() => router.back()}>
          {t("player.exit")}
        </Button>
      </div>
    )
  }

  if (state === null) {
    return (
      <div className="fixed inset-0 z-50 flex flex-col gap-4 bg-background p-6 pt-safe">
        <Skeleton className="h-10 w-full rounded-xl" />
        <Skeleton className="h-64 w-full rounded-2xl" />
      </div>
    )
  }

  // ── finished: the result review takes over ──
  if (finished) {
    return <ResultView attemptId={attemptId} />
  }

  const question = questions[index]
  const sequential = state.navigation === "sequential"
  const low = remaining !== null && remaining <= 60

  // The paper part this question belongs to; instructions show on the
  // part's FIRST question, the compact label on the rest.
  const parts = state.parts ?? []
  const currentPart = question?.part !== null && question !== undefined ? parts[question.part] : undefined
  const firstOfPart =
    question !== undefined && question.part !== null &&
    questions.findIndex((entry) => entry.part === question.part) === index

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-background">
      {/* header */}
      <header className="flex items-center gap-3 border-b bg-background/95 px-4 py-3 pt-safe backdrop-blur-xl">
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold">{state.quiz_title}</p>
          <p className="text-xs text-muted-foreground">
            {t("player.question", { number: index + 1, total: questions.length })}
          </p>
        </div>

        <span
          aria-live={low ? "assertive" : undefined}
          className={cn(
            "rounded-full px-3 py-1 font-mono text-sm font-semibold tabular-nums",
            remaining === null
              ? "bg-muted text-muted-foreground"
              : low
                ? "bg-destructive/10 text-destructive"
                : "bg-accent",
          )}
        >
          {remaining === null
            ? "∞"
            : `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, "0")}`}
        </span>

        <span className="hidden text-xs text-muted-foreground sm:block" aria-live="polite">
          {saveState === "saving"
            ? t("player.saving")
            : saveState === "offline"
              ? null
              : saveState === "saved"
                ? t("player.saved")
                : null}
        </span>

        {!sequential && (
          <Button
            variant="ghost"
            size="icon"
            className="touch-target"
            onClick={() => setPaletteOpen(true)}
            aria-label={t("player.palette")}
          >
            <LayoutGrid className="size-5" />
          </Button>
        )}
        <Button
          variant="ghost"
          size="icon"
          className="touch-target"
          onClick={() => router.back()}
          aria-label={t("player.exit")}
        >
          <X className="size-5" />
        </Button>
      </header>

      {saveState === "offline" && (
        <div className="flex items-center justify-center gap-2 bg-warning/10 px-4 py-1.5 text-xs text-warning">
          <CloudOff className="size-3.5" /> {t("player.offline")}
        </div>
      )}

      {/* question */}
      <main className="flex-1 overflow-y-auto">
        <div className="mx-auto w-full max-w-2xl px-4 py-6 md:py-10">
          {question ? (
            <div className="space-y-6">
              {currentPart !== undefined && question.part !== null && (
                <PartBanner
                  numeral={romanNumeral(question.part + 1)}
                  title={currentPart.title}
                  instructions={currentPart.instructions}
                  compact={!firstOfPart}
                />
              )}
              {question.group_id != null && state.groups?.[question.group_id] && (
                <PassageCard
                  group={state.groups[question.group_id]}
                  defaultOpen={
                    questions.findIndex((entry) => entry.group_id === question.group_id) === index
                  }
                />
              )}
              <div className="flex items-start justify-between gap-3">
                <QuestionStem
                  html={question.body.stem}
                  className="text-base font-medium leading-relaxed md:text-lg"
                />
                <span className="shrink-0 rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-muted-foreground">
                  {t("player.points", { count: question.points })}
                </span>
              </div>

              {question.body.attachments && question.body.attachments.length > 0 && (
                <QuestionAttachments attachments={question.body.attachments} />
              )}

              <QuestionInput
                key={question.question_id}
                question={question}
                value={answers[question.question_id]}
                onChange={(value) => setAnswer(question.question_id, value)}
              />
            </div>
          ) : null}
        </div>
      </main>

      {/* footer nav */}
      <footer className="border-t bg-background/95 px-4 py-3 pb-safe backdrop-blur-xl">
        <div className="mx-auto flex w-full max-w-2xl items-center gap-3">
          {!sequential && (
            <Button
              variant="outline"
              className="h-12 flex-1"
              disabled={index === 0}
              onClick={() => setIndex((prev) => Math.max(0, prev - 1))}
            >
              <ChevronLeft className="size-4" /> {t("player.previous")}
            </Button>
          )}
          {index < questions.length - 1 ? (
            <Button className="h-12 flex-1" onClick={() => setIndex((prev) => prev + 1)}>
              {t("player.next")} <ChevronRight className="size-4" />
            </Button>
          ) : (
            <Button className="h-12 flex-1" onClick={() => setConfirmSubmit(true)}>
              <Flag className="size-4" /> {t("player.finish")}
            </Button>
          )}
        </div>
      </footer>

      {/* palette */}
      <Drawer open={paletteOpen} onOpenChange={setPaletteOpen}>
        <DrawerContent>
          <DrawerHeader>
            <DrawerTitle>{t("player.palette")}</DrawerTitle>
          </DrawerHeader>
          <div className="grid grid-cols-6 gap-2 p-4 pb-8 sm:grid-cols-8">
            {questions.map((entry, entryIndex) => {
              const value = answers[entry.question_id]
              const answered = value !== undefined && value !== null && value !== ""
              const startsPart =
                entry.part !== null &&
                parts[entry.part] !== undefined &&
                (entryIndex === 0 || questions[entryIndex - 1].part !== entry.part)
              return (
                <Fragment key={entry.question_id}>
                {startsPart && entry.part !== null && (
                  <p className="col-span-full mt-1 text-xs font-semibold text-muted-foreground first:mt-0">
                    {t("exams.partLabel", { numeral: romanNumeral(entry.part + 1) })}
                    {parts[entry.part].title ? ` — ${parts[entry.part].title}` : ""}
                  </p>
                )}
                <button
                  type="button"
                  onClick={() => {
                    setIndex(entryIndex)
                    setPaletteOpen(false)
                  }}
                  className={cn(
                    "touch-target flex aspect-square items-center justify-center rounded-xl border text-sm font-medium transition-colors",
                    entryIndex === index
                      ? "border-primary bg-primary text-primary-foreground"
                      : answered
                        ? "border-success/40 bg-success/10 text-success"
                        : "text-muted-foreground",
                  )}
                >
                  {entryIndex + 1}
                </button>
                </Fragment>
              )
            })}
          </div>
        </DrawerContent>
      </Drawer>

      {/* submit confirm */}
      <AlertDialog open={confirmSubmit} onOpenChange={setConfirmSubmit}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("player.submitConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("player.submitConfirmDesc", { answered: answeredCount, total: questions.length })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("player.keepWriting")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                setConfirmSubmit(false)
                void submit()
              }}
            >
              {t("player.submitConfirmCta")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

/** The per-type answer input — big touch targets, no tiny radios. */
function QuestionInput({
  question,
  value,
  onChange,
}: {
  question: PlayerQuestion
  value: unknown
  onChange: (value: unknown) => void
}) {
  const { t } = useTranslation("lms")

  if (question.type === "mcq_single" || question.type === "mcq_multi") {
    const multi = question.type === "mcq_multi"
    const picked = new Set(
      (Array.isArray(value) ? value : value !== undefined && value !== null ? [value] : []).map(
        String,
      ),
    )

    return (
      <div className="space-y-2.5" role={multi ? "group" : "radiogroup"}>
        {(question.body.options ?? []).map((option) => {
          const isPicked = picked.has(option.id)
          return (
            <button
              key={option.id}
              type="button"
              role={multi ? "checkbox" : "radio"}
              aria-checked={isPicked}
              onClick={() => {
                if (multi) {
                  const next = new Set(picked)
                  if (isPicked) next.delete(option.id)
                  else next.add(option.id)
                  onChange([...next])
                } else {
                  onChange(option.id)
                }
              }}
              className={cn(
                "pressable flex w-full items-center gap-3 rounded-2xl border px-4 py-3.5 text-left text-sm transition-colors",
                isPicked
                  ? "border-primary bg-primary/10"
                  : "hover:bg-accent",
              )}
            >
              <span
                className={cn(
                  "flex size-6 shrink-0 items-center justify-center border text-xs font-semibold uppercase",
                  multi ? "rounded-md" : "rounded-full",
                  isPicked ? "border-primary bg-primary text-primary-foreground" : "text-muted-foreground",
                )}
              >
                {isPicked ? <Check className="size-3.5" /> : option.id}
              </span>
              <QuestionStem html={option.text} className="min-w-0 flex-1" />
            </button>
          )
        })}
      </div>
    )
  }

  if (question.type === "true_false") {
    return (
      <div className="grid grid-cols-2 gap-3">
        {[true, false].map((option) => (
          <button
            key={String(option)}
            type="button"
            onClick={() => onChange(option)}
            className={cn(
              "pressable rounded-2xl border px-4 py-5 text-sm font-medium transition-colors",
              value === option ? "border-primary bg-primary/10 text-primary" : "hover:bg-accent",
            )}
          >
            {option ? t("player.trueLabel") : t("player.falseLabel")}
          </button>
        ))}
      </div>
    )
  }

  if (question.type === "numeric") {
    return (
      <Input
        type="number"
        step="any"
        inputMode="decimal"
        className="no-spinner h-14 text-lg"
        value={value === undefined || value === null ? "" : String(value)}
        onChange={(e) => onChange(e.target.value === "" ? null : Number(e.target.value))}
        placeholder={t("player.numericPlaceholder")}
      />
    )
  }

  if (question.type === "short_answer") {
    return (
      <Input
        className="h-14 text-base"
        value={value === undefined || value === null ? "" : String(value)}
        onChange={(e) => onChange(e.target.value)}
        placeholder={t("player.yourAnswerPlaceholder")}
      />
    )
  }

  if (question.type === "essay") {
    return (
      <Textarea
        rows={10}
        value={value === undefined || value === null ? "" : String(value)}
        onChange={(e) => onChange(e.target.value)}
        placeholder={t("player.essayPlaceholder")}
      />
    )
  }

  if (question.type === "fill_blank") {
    const count = Number(question.body.blanks_count ?? 1)
    const entries = Array.isArray(value) ? value : []
    return (
      <div className="space-y-3">
        {Array.from({ length: count }).map((_, blankIndex) => (
          <div key={blankIndex} className="flex items-center gap-3">
            <span className="w-5 text-sm font-medium text-muted-foreground">{blankIndex + 1}.</span>
            <Input
              className="h-12"
              value={entries[blankIndex] === undefined || entries[blankIndex] === null ? "" : String(entries[blankIndex])}
              onChange={(e) => {
                const next = Array.from({ length: count }, (_, i) => entries[i] ?? "")
                next[blankIndex] = e.target.value
                onChange(next)
              }}
              placeholder={t("player.yourAnswerPlaceholder")}
            />
          </div>
        ))}
      </div>
    )
  }

  if (question.type === "matching") {
    const picks = (value ?? {}) as Record<string, string>
    const right = question.body.right ?? []
    return (
      <div className="space-y-3">
        {(question.body.left ?? []).map((item) => (
          <div key={item.id} className="space-y-1.5">
            <QuestionStem html={item.text} className="text-sm font-medium" />
            <div className="flex flex-wrap gap-2">
              {right.map((option) => {
                const isPicked = picks[item.id] === option.id
                return (
                  <button
                    key={option.id}
                    type="button"
                    onClick={() => onChange({ ...picks, [item.id]: option.id })}
                    className={cn(
                      "pressable rounded-full border px-3.5 py-2 text-sm transition-colors",
                      isPicked ? "border-primary bg-primary/10 text-primary" : "hover:bg-accent",
                    )}
                  >
                    <QuestionStem html={option.text} />
                  </button>
                )
              })}
            </div>
          </div>
        ))}
      </div>
    )
  }

  return null
}
