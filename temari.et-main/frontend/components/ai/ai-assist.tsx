"use client"

import { Check, ChevronDown, Copy, Sparkles } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { AiMarkdown } from "@/components/ai/ai-markdown"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { apiFetch, ApiError } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

export type AiAssistAction =
  | "quiz_questions"
  | "report_comment"
  | "lesson_week"
  | "parent_message"
  | "letter"

export interface AiDraftQuestion {
  type: "mcq_single" | "true_false" | "short_answer" | "matching" | "group"
  stem: string
  options?: { id: string; text: string }[]
  correct?: string | boolean
  matching_pairs?: { left: string; right: string }[]
  /** type "group": the passage's questions, saved under the container. */
  sub_questions?: AiDraftQuestion[]
  explanation?: string
}

const QUESTION_TYPES = ["mcq_single", "true_false", "short_answer", "matching", "passage"] as const
const DIFFICULTIES = ["mixed", "easy", "medium", "hard"] as const
const LANGUAGES = ["en", "am", "om"] as const

/**
 * The ✨ embedded AI generator: a small dialog over an existing screen that
 * calls POST /ai/actions and hands the DRAFT back to the caller (insert
 * callback) or to the clipboard. Screens stay the owner of saving — the AI
 * never writes into a studio's form directly.
 */
export function AiAssistButton({
  action,
  params = {},
  label,
  onQuestions,
  onDraft,
}: {
  action: AiAssistAction
  /** Pre-filled params the screen already knows (subject, student_id…). */
  params?: Record<string, unknown>
  label?: string
  /** quiz_questions: receive structured drafts to insert into the studio. */
  onQuestions?: (questions: AiDraftQuestion[]) => void
  /** Text actions: receive the draft (e.g. paste into the form). */
  onDraft?: (draft: string) => void
}) {
  const { t, locale } = useTranslation("ai")
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [topic, setTopic] = useState("")
  const [details, setDetails] = useState("")
  const [count, setCount] = useState(5)
  // The question-shape knobs (quiz_questions only). Subject/grade prefill
  // from what the screen knows and stay editable — a bank without a subject
  // must never dead-end in a "subject is required" error.
  const [subject, setSubject] = useState("")
  const [grade, setGrade] = useState("")
  const [difficulty, setDifficulty] = useState<(typeof DIFFICULTIES)[number]>("mixed")
  // Matching + passage stay opt-in — the everyday draft is the basic trio.
  const [types, setTypes] = useState<string[]>(["mcq_single", "true_false", "short_answer"])
  const [language, setLanguage] = useState<string>(LANGUAGES.includes(locale as (typeof LANGUAGES)[number]) ? locale : "en")
  const [notes, setNotes] = useState("")
  const [notesOpen, setNotesOpen] = useState(false)
  const [result, setResult] = useState<string | null>(null)
  const [questions, setQuestions] = useState<AiDraftQuestion[] | null>(null)
  const [copied, setCopied] = useState(false)

  const isQuiz = action === "quiz_questions"
  const needsTopic = action === "quiz_questions" || action === "lesson_week" || action === "parent_message"
  const needsDetails = action === "letter"
  const ready =
    (!needsTopic || topic.trim() !== "") &&
    (!needsDetails || details.trim() !== "") &&
    (!isQuiz || (subject.trim() !== "" && types.length > 0))

  // What the screen already knows is never asked again — the subject/grade
  // fields only appear when the owning record (an old bank) lacks them.
  const subjectProvided = String(params.subject ?? "").trim() !== ""
  const gradeProvided = String(params.grade ?? "").trim() !== ""
  /** The bank's chapter list — offered as tap-to-pick topics. */
  const topicOptions = (Array.isArray(params.topics) ? params.topics : []).filter(
    (option): option is string => typeof option === "string" && option.trim() !== "",
  )
  const supportsNotes = action === "quiz_questions" || action === "lesson_week"

  const openDialog = () => {
    if (isQuiz) {
      // Re-derive from the screen's params each time — the bank may have
      // finished loading since the last open.
      setSubject(String(params.subject ?? ""))
      setGrade(String(params.grade ?? ""))
    }
    setOpen(true)
  }

  const toggleType = (type: string) => {
    setTypes((prev) => (prev.includes(type) ? prev.filter((v) => v !== type) : [...prev, type]))
  }

  const run = async () => {
    setBusy(true)
    setResult(null)
    setQuestions(null)
    try {
      const res = await apiFetch<{ data: Record<string, unknown> }>("/ai/actions", {
        method: "POST",
        body: {
          action,
          params: {
            ...params,
            ...(action === "quiz_questions"
              ? { subject, grade, topic, count, difficulty, types, language }
              : {}),
            ...(action === "lesson_week" ? { unit: topic } : {}),
            ...(supportsNotes && notes.trim() !== "" ? { notes: notes.trim() } : {}),
            ...(action === "parent_message" ? { topic } : {}),
            ...(action === "letter" ? { details } : {}),
          },
        },
      })

      if (action === "quiz_questions") {
        setQuestions((res.data.questions as AiDraftQuestion[]) ?? [])
      } else {
        setResult(String(res.data.draft ?? res.data.comment ?? ""))
      }
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("thread.error"))
    } finally {
      setBusy(false)
    }
  }

  const copyResult = () => {
    if (result === null) return
    void navigator.clipboard.writeText(result).then(() => {
      setCopied(true)
      window.setTimeout(() => setCopied(false), 1500)
    })
  }

  return (
    <>
      <Button variant="outline" size="sm" onClick={openDialog}>
        <Sparkles className="size-4 text-primary" />
        {label ?? t("assist.button")}
      </Button>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Sparkles className="size-4 text-primary" /> {t(`assist.titles.${action}`)}
            </DialogTitle>
            <DialogDescription>{t("assist.draftNote")}</DialogDescription>
          </DialogHeader>

          <div className="space-y-3">
            {isQuiz && (!subjectProvided || !gradeProvided) && (
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {!subjectProvided && (
                  <div className="space-y-1.5">
                    <Label>{t("assist.subject")}</Label>
                    <Input value={subject} onChange={(e) => setSubject(e.target.value)} maxLength={80} />
                  </div>
                )}
                {!gradeProvided && (
                  <div className="space-y-1.5">
                    <Label>{t("assist.grade")}</Label>
                    <Input value={grade} onChange={(e) => setGrade(e.target.value)} maxLength={40} />
                  </div>
                )}
              </div>
            )}
            {needsTopic && (
              <div className="space-y-1.5">
                <Label>{t(`assist.topicLabel.${action}`)}</Label>
                {topicOptions.length > 0 && (
                  <div className="flex flex-wrap gap-2 pb-0.5">
                    {topicOptions.slice(0, 12).map((option) => {
                      const selected = topic === option
                      return (
                        <button
                          key={option}
                          type="button"
                          onClick={() => setTopic(selected ? "" : option)}
                          aria-pressed={selected}
                          className={cn(
                            "pressable min-h-10 rounded-full border px-3.5 py-1.5 text-sm transition-colors",
                            selected
                              ? "border-primary bg-primary/10 font-medium text-primary"
                              : "bg-card text-muted-foreground hover:bg-accent hover:text-foreground",
                          )}
                        >
                          {selected && <Check className="me-1.5 inline size-3.5" aria-hidden />}
                          {option}
                        </button>
                      )
                    })}
                  </div>
                )}
                <Input
                  value={topic}
                  onChange={(e) => setTopic(e.target.value)}
                  maxLength={200}
                  placeholder={topicOptions.length > 0 ? t("assist.topicCustom") : undefined}
                />
              </div>
            )}
            {isQuiz && (
              <>
                <div className="space-y-1.5">
                  <Label>{t("assist.types")}</Label>
                  <div className="flex flex-wrap gap-2">
                    {QUESTION_TYPES.map((type) => {
                      const selected = types.includes(type)
                      return (
                        <button
                          key={type}
                          type="button"
                          onClick={() => toggleType(type)}
                          aria-pressed={selected}
                          className={cn(
                            "pressable min-h-10 rounded-full border px-3.5 py-1.5 text-sm transition-colors",
                            selected
                              ? "border-primary bg-primary/10 font-medium text-primary"
                              : "bg-card text-muted-foreground hover:bg-accent hover:text-foreground",
                          )}
                        >
                          {selected && <Check className="me-1.5 inline size-3.5" aria-hidden />}
                          {t(`assist.typeOptions.${type}`)}
                        </button>
                      )
                    })}
                  </div>
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                  <div className="space-y-1.5">
                    <Label>{t("assist.difficulty")}</Label>
                    <Select value={difficulty} onValueChange={(v) => setDifficulty(v as (typeof DIFFICULTIES)[number])}>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {DIFFICULTIES.map((option) => (
                          <SelectItem key={option} value={option}>
                            {t(`assist.difficultyOptions.${option}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>{t("assist.language")}</Label>
                    <Select value={language} onValueChange={setLanguage}>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {LANGUAGES.map((option) => (
                          <SelectItem key={option} value={option}>
                            {t(`assist.languageOptions.${option}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>{t("assist.count")}</Label>
                    <Input
                      type="number"
                      min={1}
                      max={10}
                      value={count}
                      onChange={(e) => setCount(Math.min(10, Math.max(1, Number(e.target.value) || 5)))}
                    />
                  </div>
                </div>
              </>
            )}
            {needsDetails && (
              <div className="space-y-1.5">
                <Label>{t("assist.detailsLabel")}</Label>
                <Textarea
                  value={details}
                  onChange={(e) => setDetails(e.target.value)}
                  rows={4}
                  maxLength={1000}
                />
              </div>
            )}

            {supportsNotes && (
              <div className="space-y-1.5">
                <button
                  type="button"
                  onClick={() => setNotesOpen((v) => !v)}
                  aria-expanded={notesOpen}
                  className="flex items-center gap-1 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                  <ChevronDown className={cn("size-4 transition-transform", !notesOpen && "-rotate-90")} aria-hidden />
                  {t("assist.customInstructions")}
                  {!notesOpen && notes.trim() !== "" && <span className="size-1.5 rounded-full bg-primary" aria-hidden />}
                </button>
                {notesOpen && (
                  <Textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={3}
                    maxLength={500}
                    placeholder={t("assist.customInstructionsHint")}
                  />
                )}
              </div>
            )}

            <Button onClick={() => void run()} loading={busy} disabled={!ready} className="w-full">
              {t("assist.generate")}
            </Button>

            {questions !== null && (
              <div className="space-y-2">
                {questions.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t("thread.error")}</p>
                ) : (
                  <>
                    <div className="max-h-72 space-y-2 overflow-y-auto rounded-xl border p-3">
                      {questions.map((question, index) =>
                        question.type === "group" ? (
                          <div key={index} className="space-y-2 rounded-lg border border-dashed p-2 text-sm">
                            <p className="line-clamp-4 whitespace-pre-wrap text-muted-foreground">
                              {question.stem}
                            </p>
                            {(question.sub_questions ?? []).map((sub, subIndex) => (
                              <DraftQuestionPreview
                                key={subIndex}
                                question={sub}
                                label={`${index + 1}.${subIndex + 1}`}
                              />
                            ))}
                          </div>
                        ) : (
                          <DraftQuestionPreview
                            key={index}
                            question={question}
                            label={String(index + 1)}
                          />
                        ),
                      )}
                    </div>
                    {onQuestions && (
                      <Button
                        className="w-full"
                        onClick={() => {
                          onQuestions(questions)
                          setOpen(false)
                        }}
                      >
                        {t("assist.insert")}
                      </Button>
                    )}
                  </>
                )}
              </div>
            )}

            {result !== null && result !== "" && (
              <div className="space-y-2">
                <div className="max-h-72 overflow-y-auto rounded-xl border p-3">
                  <AiMarkdown content={result} />
                </div>
                <div className="flex gap-2">
                  <Button variant="outline" className="flex-1" onClick={copyResult}>
                    {copied ? <Check className="size-4" /> : <Copy className="size-4" />}
                    {t("thread.copy")}
                  </Button>
                  {onDraft && (
                    <Button
                      className="flex-1"
                      onClick={() => {
                        onDraft(result)
                        setOpen(false)
                      }}
                    >
                      {t("assist.insert")}
                    </Button>
                  )}
                </div>
              </div>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}

/** One draft question (never a group) in the ✨ preview list. */
function DraftQuestionPreview({
  question,
  label,
}: {
  question: AiDraftQuestion
  label: string
}) {
  return (
    <div className="text-sm">
      <p className="font-medium">
        {label}. {question.stem}
      </p>
      {question.options?.map((option) => (
        <p
          key={option.id}
          className={
            question.correct === option.id
              ? "ps-3 font-medium text-primary"
              : "ps-3 text-muted-foreground"
          }
        >
          {option.id}) {option.text}
        </p>
      ))}
      {question.type === "matching" &&
        question.matching_pairs?.map((pair, index) => (
          <p key={index} className="ps-3 text-muted-foreground">
            {pair.left} ↔ {pair.right}
          </p>
        ))}
      {(question.type === "true_false" || question.type === "short_answer") && (
        <p className="ps-3 text-muted-foreground">→ {String(question.correct)}</p>
      )}
    </div>
  )
}
