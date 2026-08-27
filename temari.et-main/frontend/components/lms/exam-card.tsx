"use client"
import { Clock, FileQuestion, FileText, KeyRound, Lock, Play, Trophy } from "lucide-react"
import { useRouter } from "next/navigation"
import { useState } from "react"
import { toast } from "sonner"

import { QuestionStem } from "@/components/lms/question-content"
import { formatDateTime } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AttemptState, MeExam } from "@/lib/types"
import { cn } from "@/lib/utils"

type ExamAction = "resume" | "start" | "result" | null

/**
 * One exam as the taker sees it, styled as an app-native list card: a state
 * medallion leads, the whole card is the tap target, and a trailing chip names
 * the one thing to do next — Resume / Start / View result. Access-coded exams
 * open the lobby (title, rules, code) before the deliberate start.
 */
export function StudentExamCard({ exam }: { exam: MeExam }) {
  const { t } = useTranslation("lms")
  const router = useRouter()

  const [codeOpen, setCodeOpen] = useState(false)
  const [code, setCode] = useState("")
  const [starting, setStarting] = useState(false)

  async function start(accessCode?: string) {
    setStarting(true)
    try {
      const res = await apiFetch<{ data: AttemptState }>(`/me/exams/${exam.id}/start`, {
        method: "POST",
        body: accessCode ? { access_code: accessCode } : {},
      })
      router.push(`/me/exam/${res.data.attempt_id}`)
    } catch (error) {
      setStarting(false)
      setCodeOpen(false)
      toast.error(error instanceof ApiError ? error.message : t("player.loadFailed"))
    }
  }

  // The single next step, in priority order.
  const action: ExamAction = exam.live_attempt_id
    ? "resume"
    : exam.can_start
      ? "start"
      : exam.result_attempt_id
        ? "result"
        : null

  function onCardTap() {
    if (action === "resume") router.push(`/me/exam/${exam.live_attempt_id}`)
    else if (action === "start") setCodeOpen(true)
    else if (action === "result") router.push(`/me/exam/${exam.result_attempt_id}`)
  }

  const attemptsLeft = Math.max(0, exam.attempts_allowed - exam.attempts_used)
  const hasScore = exam.best_score !== undefined && exam.best_score !== null
  const passed =
    hasScore && exam.best_max_score ? Number(exam.best_score) / Number(exam.best_max_score) >= 0.5 : true

  // Medallion — the at-a-glance state signal.
  const medallion =
    action === "resume"
      ? { icon: Play, tone: "bg-warning/12 text-warning" }
      : action === "start"
        ? { icon: Play, tone: "bg-primary/12 text-primary" }
        : action === "result"
          ? { icon: Trophy, tone: passed ? "bg-success/12 text-success" : "bg-destructive/10 text-destructive" }
          : { icon: Lock, tone: "bg-muted text-muted-foreground" }
  const MedallionIcon = medallion.icon

  const Wrapper = action ? "button" : "div"

  return (
    <div
      className={cn(
        "group rounded-2xl border bg-card shadow-xs transition-colors",
        action && "hover:border-primary/30 hover:bg-accent/40",
      )}
    >
      <Wrapper
        type={action ? "button" : undefined}
        onClick={action ? onCardTap : undefined}
        className={cn(
          "flex w-full items-center gap-3.5 p-3.5 text-left",
          action && "pressable cursor-pointer",
        )}
      >
        {/* state medallion */}
        <span
          className={cn(
            "flex size-12 shrink-0 items-center justify-center rounded-2xl",
            medallion.tone,
          )}
        >
          {action === "result" && hasScore ? (
            <span className="text-sm font-semibold tabular-nums leading-none">
              {Number(exam.best_score)}
            </span>
          ) : (
            <MedallionIcon className="size-5" strokeWidth={1.75} />
          )}
        </span>

        {/* body */}
        <div className="min-w-0 flex-1">
          <div className="flex items-start gap-2">
            <p className="min-w-0 flex-1 text-sm font-medium leading-snug line-clamp-2">
              {exam.title}
            </p>
            <Badge variant="secondary" className="shrink-0 text-[10px]">
              {exam.exam_kind
                ? t(`exams.examKinds.${exam.exam_kind}`)
                : t(`exams.kinds.${exam.kind}`)}
            </Badge>
          </div>

          {(exam.subject_name || exam.section_name || exam.grade_level_name) && (
            <p className="mt-0.5 truncate text-xs text-muted-foreground">
              {[exam.subject_name, exam.grade_level_name ?? exam.section_name]
                .filter(Boolean)
                .join(" · ")}
            </p>
          )}

          <div className="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-muted-foreground">
            {exam.mode === "practice" && (
              <span className="font-medium text-success">{t("prep.practiceMode")}</span>
            )}
            {(exam.exam_year_ec || exam.stream) && (
              <span>
                {[
                  exam.exam_year_ec ? `${exam.exam_year_ec} ${t("prep.ec")}` : null,
                  exam.stream
                    ? t(`courses.stream${exam.stream === "natural" ? "Natural" : "Social"}`)
                    : null,
                ]
                  .filter(Boolean)
                  .join(" · ")}
              </span>
            )}
            <span className="inline-flex items-center gap-1">
              <FileQuestion className="size-3" /> {exam.question_count}
            </span>
            <span className="inline-flex items-center gap-1">
              <Clock className="size-3" />
              {exam.duration_minutes
                ? t("learn.minutes", { count: exam.duration_minutes })
                : t("learn.noTimeLimit")}
            </span>
            {exam.requires_access_code && action !== "result" && (
              <KeyRound className="size-3" aria-label={t("exams.accessCode")} />
            )}
            {exam.closes_at && exam.window_open && (
              <span>{t("learn.closesAt", { date: formatDateTime(exam.closes_at) })}</span>
            )}
            {exam.closes_at && !exam.window_open && exam.status === "published" && action !== "result" && (
              <span>{t("learn.opensAt", { date: formatDateTime(exam.opens_at) })}</span>
            )}
            {hasScore && exam.best_max_score ? (
              <span
                className={cn(
                  "inline-flex items-center gap-1 font-medium tabular-nums",
                  passed ? "text-success" : "text-destructive",
                )}
              >
                <Trophy className="size-3" /> {Number(exam.best_score)}/{Number(exam.best_max_score)}
              </span>
            ) : action === "start" && exam.attempts_allowed !== 0 ? (
              <span>{t("learn.attemptsLeft", { count: attemptsLeft })}</span>
            ) : action === null ? (
              <span>{exam.status === "closed" ? t("learn.examClosed") : t("learn.noAttemptsLeft")}</span>
            ) : null}
          </div>
        </div>

        {/* trailing action chip */}
        {action && (
          <span
            className={cn(
              "inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full px-3.5 text-xs font-medium transition-transform group-active:scale-95",
              action === "result"
                ? "border bg-background text-foreground"
                : "bg-primary text-primary-foreground shadow-xs",
            )}
          >
            {action === "result" ? (
              <>
                <FileText className="size-3.5" /> {t("learn.viewResult")}
              </>
            ) : (
              <>
                <Play className="size-3.5" />
                {action === "resume" ? t("learn.resume") : t("learn.start")}
              </>
            )}
          </span>
        )}
      </Wrapper>

      {/* The lobby: title + instructions first, then the deliberate start. */}
      <Dialog open={codeOpen} onOpenChange={setCodeOpen}>
        <DialogContent className="flex max-h-[90dvh] flex-col overflow-hidden sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{exam.title}</DialogTitle>
            <DialogDescription>{t("learn.lobbyDesc")}</DialogDescription>
          </DialogHeader>
          {exam.instructions && (
            <div className="min-h-0 shrink overflow-y-auto rounded-xl border bg-muted/30 p-3.5">
              <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t("exams.instructions")}
              </p>
              <QuestionStem html={exam.instructions} className="text-sm leading-relaxed" />
            </div>
          )}
          <div className="grid grid-cols-2 gap-2 text-sm">
            <div className="rounded-xl bg-muted/50 px-3 py-2.5">
              <p className="text-xs text-muted-foreground">{t("exams.questionCount")}</p>
              <p className="font-semibold tabular-nums">{exam.question_count}</p>
            </div>
            <div className="rounded-xl bg-muted/50 px-3 py-2.5">
              <p className="text-xs text-muted-foreground">{t("exams.duration")}</p>
              <p className="font-semibold">
                {exam.duration_minutes
                  ? t("learn.minutes", { count: exam.duration_minutes })
                  : t("learn.noTimeLimit")}
              </p>
            </div>
            <div className="rounded-xl bg-muted/50 px-3 py-2.5">
              <p className="text-xs text-muted-foreground">{t("exams.attemptsAllowed")}</p>
              <p className="font-semibold">
                {exam.attempts_allowed === 0
                  ? t("learn.unlimitedAttempts")
                  : `${attemptsLeft}/${exam.attempts_allowed}`}
              </p>
            </div>
            <div className="rounded-xl bg-muted/50 px-3 py-2.5">
              <p className="text-xs text-muted-foreground">{t("learn.lobbyOnce")}</p>
              <p className="font-semibold">
                {exam.mode === "practice" ? t("prep.practiceShort") : t("learn.lobbyTimerRuns")}
              </p>
            </div>
          </div>
          <p className="text-xs text-muted-foreground">{t("learn.lobbyRules")}</p>
          {exam.requires_access_code && (
            <Input
              value={code}
              onChange={(e) => setCode(e.target.value.toUpperCase())}
              placeholder={t("learn.accessCodePlaceholder")}
              className="h-12 text-center font-mono text-lg tracking-widest"
              autoFocus
            />
          )}
          <DialogFooter>
            <Button
              className="h-12 w-full"
              loading={starting}
              disabled={exam.requires_access_code && code.trim() === ""}
              onClick={() => void start(exam.requires_access_code ? code.trim() : undefined)}
            >
              <Play className="size-4" /> {t("learn.lobbyStart")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
