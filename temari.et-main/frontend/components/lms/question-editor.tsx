"use client"

import {
  ArrowDown,
  ArrowUp,
  Check,
  ChevronDown,
  Eye,
  FileUp,
  HelpCircle,
  Link2,
  Loader2,
  Pencil,
  Plus,
  Sparkles,
  Trash2,
  X,
  CirclePlay,
} from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { bankPreviewQuiz, ExamPreview } from "@/components/lms/exam-preview"
import { QuestionAttachments, stemText, TYPE_GROUP_ORDER } from "@/components/lms/question-content"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { RichTextEditor } from "@/components/ui/rich-text"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { stripHtml } from "@/lib/sanitize-html"
import type {
  Question,
  QuestionAttachment,
  QuestionBank,
  QuestionOption,
  QuestionType,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const QUESTION_FILE_ACCEPT = ".jpg,.jpeg,.png,.webp,.gif,.pdf,.mp3,.mp4,.doc,.docx,.ppt,.pptx,.xls,.xlsx"

const TYPES: QuestionType[] = [
  "mcq_single",
  "mcq_multi",
  "true_false",
  "short_answer",
  "numeric",
  "fill_blank",
  "matching",
  "essay",
  "group",
]

const OPTION_IDS = "abcdefghij".split("")

/** First unused letter — deleting then re-adding options never collides. */
function nextOptionId(existing: QuestionOption[]): string {
  const used = new Set(existing.map((o) => o.id))
  return OPTION_IDS.find((id) => !used.has(id)) ?? `x${existing.length + 1}`
}

function defaultOptions(): QuestionOption[] {
  // Ethiopian exams run A–D, so seed 4 choices.
  return OPTION_IDS.slice(0, 4).map((id) => ({ id, text: "" }))
}

interface Props {
  bank: QuestionBank | null
  question: Question | null
  /** Creating a sub-question inside this group container (passage). */
  parent?: Question | null
  /** For a group (passage): its existing sub-questions, authored order. */
  subQuestions?: Question[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: (question: Question) => void
  /**
   * Fired when the passage's CONTENTS changed (a sub-question was added,
   * edited or removed) — refresh lists without treating it as "the edited
   * question was saved" (screens like the exam studio react to onSaved by
   * picking the question onto the paper).
   */
  onChanged?: () => void
}

/**
 * The full-screen question studio (ADR-016). Left: the question itself —
 * rich stem with inline images, the type-shaped answer area, reference
 * attachments. Right: everything about the question (type, points,
 * difficulty, topic, status…). Saving is a split button: publish, or keep
 * as a draft. On mobile it becomes a single app-like scroll.
 */
export function QuestionEditor({ bank, question, parent = null, subQuestions, open, onOpenChange, onSaved, onChanged }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  // Sub-questions live inside a group; the group itself can't nest.
  const insideGroup = parent !== null || Boolean(question?.parent_id)
  const typeChoices = insideGroup ? TYPES.filter((option) => option !== "group") : TYPES

  // A brand-new passage auto-saves the moment its first question is added —
  // from then on this editor keeps updating THAT row.
  const [savedGroup, setSavedGroup] = useState<Question | null>(null)
  const effectiveQuestion = question ?? savedGroup

  // The passage's questions — created and managed right here, never from
  // the bank table. Seeded from the prop on open; kept locally afterwards
  // so a background list refresh never resets the editor mid-edit.
  const [subs, setSubs] = useState<Question[]>([])
  const subsSeed = useRef<Question[]>([])
  useEffect(() => {
    subsSeed.current = subQuestions ?? []
  }, [subQuestions])
  const [childEditing, setChildEditing] = useState<Question | null>(null)
  const [childOpen, setChildOpen] = useState(false)
  const [groupPreviewOpen, setGroupPreviewOpen] = useState(false)

  const [type, setType] = useState<QuestionType>("mcq_single")
  const [stem, setStem] = useState("")
  const [points, setPoints] = useState("1")
  const [difficulty, setDifficulty] = useState<string>("")
  const [topic, setTopic] = useState("")
  const [status, setStatus] = useState<"draft" | "published" | "retired">("published")
  const [tags, setTags] = useState("")
  const [source, setSource] = useState("")
  const [explanation, setExplanation] = useState("")
  const [attachments, setAttachments] = useState<QuestionAttachment[]>([])

  const [options, setOptions] = useState<QuestionOption[]>(defaultOptions())
  const [correctSingle, setCorrectSingle] = useState("")
  const [correctMulti, setCorrectMulti] = useState<string[]>([])
  const [tfCorrect, setTfCorrect] = useState(true)
  const [accepted, setAccepted] = useState("")
  const [numericValue, setNumericValue] = useState("")
  const [tolerance, setTolerance] = useState("0")
  const [blanks, setBlanks] = useState<string[]>([""])
  const [left, setLeft] = useState<QuestionOption[]>([
    { id: "l1", text: "" },
    { id: "l2", text: "" },
  ])
  const [right, setRight] = useState<QuestionOption[]>([
    { id: "r1", text: "" },
    { id: "r2", text: "" },
  ])
  const [pairs, setPairs] = useState<Record<string, string>>({})
  const [rubric, setRubric] = useState("")

  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)
  const [uploadingFile, setUploadingFile] = useState(false)
  const [imgUploading, setImgUploading] = useState(false)
  // Stem + every option editor report uploads here — Save stays disabled
  // until the LAST in-flight image lands, not just the most recent one.
  const uploadCount = useRef(0)
  const trackUploading = (up: boolean) => {
    uploadCount.current = Math.max(0, uploadCount.current + (up ? 1 : -1))
    setImgUploading(uploadCount.current > 0)
  }
  const [linkDraft, setLinkDraft] = useState<{ kind: "link" | "youtube"; url: string; name: string } | null>(null)

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- sync editor with the edited row */
    setErrors({})
    setSavedGroup(null)
    setSubs(subsSeed.current)
    setChildEditing(null)
    setChildOpen(false)
    setType(question?.type ?? "mcq_single")
    setStem(question?.body.stem ?? "")
    setPoints(question ? String(question.points) : "1")
    setDifficulty(question?.difficulty ?? "")
    setTopic(question?.topic ?? "")
    setStatus(question?.status ?? "published")
    setTags((question?.tags ?? []).join(", "))
    setSource(question?.source ?? "")
    setExplanation(question?.explanation ?? "")
    setAttachments(question?.body.attachments?.map((a) => ({ ...a })) ?? [])
    setOptions(
      question?.body.options?.length
        ? question.body.options.map((o) => ({ ...o }))
        : defaultOptions(),
    )
    const key = question?.answer_key
    setCorrectSingle(typeof key?.correct === "string" ? key.correct : "")
    setCorrectMulti(Array.isArray(key?.correct) ? key.correct.map(String) : [])
    setTfCorrect(typeof key?.correct === "boolean" ? key.correct : true)
    setAccepted((key?.accepted ?? []).join("\n"))
    setNumericValue(key?.value !== undefined ? String(key.value) : "")
    setTolerance(key?.tolerance !== undefined ? String(key.tolerance) : "0")
    setBlanks(key?.blanks?.length ? key.blanks.map((b) => b.join("\n")) : [""])
    setLeft(
      question?.body.left?.length
        ? question.body.left.map((o) => ({ ...o }))
        : [
            { id: "l1", text: "" },
            { id: "l2", text: "" },
          ],
    )
    setRight(
      question?.body.right?.length
        ? question.body.right.map((o) => ({ ...o }))
        : [
            { id: "r1", text: "" },
            { id: "r2", text: "" },
          ],
    )
    setPairs((key?.pairs as Record<string, string>) ?? {})
    setRubric(key?.rubric ?? "")
    setLinkDraft(null)
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [open, question])

  const bankTopics = useMemo(() => {
    const list = [...(bank?.topics ?? [])]
    if (question?.topic && !list.some((x) => x.toLowerCase() === question.topic!.toLowerCase())) {
      list.push(question.topic)
    }
    return list
  }, [bank, question])

  // The passage as a student-facing dry run: the CURRENT stem over the saved
  // sub-questions, wrapped exactly like the bank preview.
  const groupPreviewQuiz = useMemo(() => {
    if (type !== "group" || effectiveQuestion === null) return null
    const container = {
      ...effectiveQuestion,
      body: { ...effectiveQuestion.body, stem },
      status: "published",
    } as Question
    return bankPreviewQuiz(
      stemText(stem).slice(0, 80) || bank?.name || t("questions.types.group"),
      [container, ...subs],
      { grade: bank?.grade_level_name, subject: bank?.subject_name },
    )
  }, [type, effectiveQuestion, stem, subs, bank, t])

  async function uploadMedia(file: File) {
    const form = new FormData()
    form.append("file", file)
    const res = await apiFetch<{ data: { name: string; path: string; size: number; mime_type: string; url: string } }>(
      `/question-banks/${bank?.id}/uploads`,
      { method: "POST", body: form },
    )
    return res.data
  }

  /** Client-side coherence check — mirror of QuestionRules, friendly inline. */
  /** A rich value counts as filled when it has text, an image or a formula. */
  function richFilled(html: string): boolean {
    return stripHtml(html).trim() !== "" || /<img|data-math/i.test(html)
  }

  function validate(): Record<string, string> {
    const found: Record<string, string> = {}
    if (!richFilled(stem)) found.stem = t("questions.errors.stemRequired")

    if (type === "mcq_single" || type === "mcq_multi") {
      const filled = options.filter((o) => richFilled(o.text))
      if (filled.length < 2) {
        found.options = t("questions.errors.optionsMin")
      } else if (type === "mcq_single") {
        if (!filled.some((o) => o.id === correctSingle)) found.options = t("questions.errors.pickCorrect")
      } else if (!correctMulti.some((id) => filled.some((o) => o.id === id))) {
        found.options = t("questions.errors.pickCorrectMulti")
      }
    }
    if (type === "numeric" && numericValue.trim() === "") {
      found.numeric = t("questions.errors.valueRequired")
    }
    if (type === "fill_blank" && blanks.some((b) => b.split("\n").every((l) => l.trim() === ""))) {
      found.blanks = t("questions.errors.blanksRequired")
    }
    if (type === "matching") {
      const l = left.filter((o) => o.text.trim() !== "")
      const r = right.filter((o) => o.text.trim() !== "")
      if (l.length < 2 || r.length < 2) {
        found.matching = t("questions.errors.sidesMin")
      } else if (!l.every((item) => pairs[item.id] && r.some((o) => o.id === pairs[item.id]))) {
        found.matching = t("questions.errors.pairsRequired")
      }
    }
    return found
  }

  function buildPayload(saveStatus: string) {
    const body: Record<string, unknown> = { stem }
    if (attachments.length > 0) body.attachments = attachments
    const key: Record<string, unknown> = {}

    if (type === "mcq_single" || type === "mcq_multi") {
      body.options = options.filter((o) => richFilled(o.text))
      if (type === "mcq_single") key.correct = correctSingle
      else key.correct = correctMulti.filter((id) => options.some((o) => o.id === id && richFilled(o.text)))
    } else if (type === "true_false") {
      key.correct = tfCorrect
    } else if (type === "short_answer") {
      key.accepted = accepted
        .split("\n")
        .map((line) => line.trim())
        .filter(Boolean)
    } else if (type === "numeric") {
      key.value = Number(numericValue)
      key.tolerance = Number(tolerance || 0)
    } else if (type === "fill_blank") {
      key.blanks = blanks.map((block) =>
        block
          .split("\n")
          .map((line) => line.trim())
          .filter(Boolean),
      )
    } else if (type === "matching") {
      body.left = left.filter((o) => o.text.trim() !== "")
      body.right = right.filter((o) => o.text.trim() !== "")
      key.pairs = Object.fromEntries(
        Object.entries(pairs).filter(([l]) => (body.left as QuestionOption[]).some((o) => o.id === l)),
      )
    } else if (type === "essay" && rubric.trim()) {
      key.rubric = rubric.trim()
    }

    return {
      type,
      body,
      answer_key: type !== "group" && Object.keys(key).length > 0 ? key : null,
      ...(parent !== null && question === null ? { parent_id: parent.id } : {}),
      points: Number(points || 1),
      difficulty: difficulty || null,
      topic: topic.trim() || null,
      status: saveStatus,
      tags: tags
        .split(",")
        .map((tag) => tag.trim())
        .filter(Boolean),
      source: source || null,
      explanation: explanation || null,
    }
  }

  async function save(saveStatus?: "draft" | "published", stayOpen = false): Promise<Question | null> {
    const finalStatus = saveStatus ?? status
    const clientErrors = validate()
    setErrors(clientErrors)
    if (Object.keys(clientErrors).length > 0) {
      toast.error(t("questions.errors.fix"))
      return null
    }

    setSaving(true)
    try {
      const res = await apiFetch<{ data: Question }>(
        effectiveQuestion ? `/questions/${effectiveQuestion.id}` : `/question-banks/${bank?.id}/questions`,
        { method: effectiveQuestion ? "PUT" : "POST", body: buildPayload(finalStatus) },
      )
      toast.success(finalStatus === "draft" ? t("questions.savedDraft") : t("questions.saved"))
      if (stayOpen) {
        // A new passage keeps editing its now-saved row.
        if (question === null) setSavedGroup(res.data)
        onSaved(res.data)
        return res.data
      }
      onOpenChange(false)
      onSaved(res.data)
      return res.data
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        const flat: Record<string, string> = {}
        for (const [field, messages] of Object.entries(error.errors)) {
          if (field.startsWith("body.options") || field.includes("correct")) flat.options = messages[0]
          else if (field.startsWith("body.stem")) flat.stem = messages[0]
          else if (field.includes("blanks")) flat.blanks = messages[0]
          else if (field.includes("pairs") || field.includes("left")) flat.matching = messages[0]
          else if (field.includes("value")) flat.numeric = messages[0]
          else flat.general = messages[0]
        }
        setErrors(flat)
      }
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      return null
    } finally {
      setSaving(false)
    }
  }

  /**
   * "Add question" inside a passage: a brand-new passage saves itself first
   * (its questions need the container's id), then the sub-question editor
   * opens on top — one flow, no dead ends.
   */
  async function addSubQuestion() {
    if (effectiveQuestion === null) {
      const saved = await save(undefined, true)
      if (saved === null) return
    }
    setChildEditing(null)
    setChildOpen(true)
  }

  function handleChildSaved(child: Question) {
    setSubs((prev) => {
      const index = prev.findIndex((sub) => sub.id === child.id)
      if (index === -1) return [...prev, child]
      return prev.map((sub) => (sub.id === child.id ? child : sub))
    })
    onChanged?.()
  }

  /**
   * Persist a new sub-question order (optimistic — reverts on failure).
   * Position is what every surface reads: bank, exam paper, player, PDF.
   */
  async function persistSubOrder(ordered: Question[]) {
    if (effectiveQuestion === null) return
    const before = subs
    setSubs(ordered)
    try {
      await apiFetch(`/questions/${effectiveQuestion.id}/reorder`, {
        method: "POST",
        body: { question_ids: ordered.map((sub) => sub.id) },
      })
      onChanged?.()
    } catch (error) {
      setSubs(before)
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  /** Same one-click arrangement as the exam studio: types in paper order. */
  function groupSubsByType() {
    const ordered = TYPE_GROUP_ORDER.flatMap((groupType) => subs.filter((sub) => sub.type === groupType))
    for (const sub of subs) {
      if (!ordered.includes(sub)) ordered.push(sub)
    }
    void persistSubOrder(ordered)
  }

  function moveSub(index: number, delta: -1 | 1) {
    const target = index + delta
    if (target < 0 || target >= subs.length) return
    const ordered = [...subs]
    ;[ordered[index], ordered[target]] = [ordered[target], ordered[index]]
    void persistSubOrder(ordered)
  }

  async function deleteChild(child: Question) {
    try {
      const res = await apiFetch<{ message: string }>(`/questions/${child.id}`, { method: "DELETE" })
      toast.success(res.message)
      setSubs((prev) => prev.filter((sub) => sub.id !== child.id))
      onChanged?.()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  function setOption(index: number, text: string) {
    setOptions((prev) => prev.map((o, i) => (i === index ? { ...o, text } : o)))
  }

  function addLinkAttachment() {
    if (!linkDraft || !/^https?:\/\//i.test(linkDraft.url.trim())) return
    setAttachments((prev) => [
      ...prev,
      {
        kind: linkDraft.kind,
        url: linkDraft.url.trim(),
        name: linkDraft.name.trim() || null,
      },
    ])
    setLinkDraft(null)
  }

  async function addFileAttachment(file: File) {
    setUploadingFile(true)
    try {
      const stored = await uploadMedia(file)
      setAttachments((prev) => [...prev, { kind: "file", ...stored }])
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setUploadingFile(false)
    }
  }

  // Picked or dropped, an attachment uploads through the same path (its name
  // stays inline-editable in the attachment list afterwards).
  const attachmentDrop = useFileDrop({
    accept: QUESTION_FILE_ACCEPT,
    disabled: uploadingFile,
    onFiles: ([file]) => void addFileAttachment(file),
  })

  // The type shapes the whole answer area, so on mobile (where the settings
  // rail stacks BELOW the canvas) it must come first. One controlled tree,
  // mounted twice: a setup card above the canvas (mobile) and the top of the
  // settings rail (desktop).
  const typeSetup = (
    <>
      <div className="space-y-2">
        <Label>{t("questions.type")}</Label>
        <Select
          value={type}
          onValueChange={(v) => setType(v as QuestionType)}
          disabled={question !== null}
        >
          <SelectTrigger className="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {typeChoices.map((option) => (
              <SelectItem key={option} value={option}>
                {t(`questions.types.${option}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {question !== null && (
          <p className="text-xs text-muted-foreground">{t("questions.typeLocked")}</p>
        )}
      </div>

      {/* A group carries no points/difficulty of its own — its
          sub-questions do. */}
      <div className={cn("grid grid-cols-2 gap-3", type === "group" && "hidden")}>
        <div className="space-y-2">
          <Label>{t("questions.points")}</Label>
          <Input
            type="number"
            min="0.25"
            step="0.25"
            className="no-spinner"
            value={points}
            onChange={(e) => setPoints(e.target.value)}
          />
        </div>
        <div className="space-y-2">
          <Label>{t("questions.difficulty")}</Label>
          <Select
            value={difficulty || "any"}
            onValueChange={(v) => setDifficulty(v === "any" ? "" : v)}
          >
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="any">{t("questions.difficultyAny")}</SelectItem>
              <SelectItem value="easy">{t("questions.easy")}</SelectItem>
              <SelectItem value="medium">{t("questions.medium")}</SelectItem>
              <SelectItem value="hard">{t("questions.hard")}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </>
  )

  return (
    <>
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/20 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col bg-background data-open:animate-in data-open:fade-in-0 data-open:zoom-in-[0.99] data-closed:animate-out data-closed:fade-out-0"
          onEscapeKeyDown={(e) => e.preventDefault()}
          onPointerDownOutside={(e) => e.preventDefault()}
          onInteractOutside={(e) => e.preventDefault()}
        >
          {/* ── Top bar ─────────────────────────────────────────────── */}
          <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-3 md:px-5">
            <Button
              variant="ghost"
              size="icon"
              className="text-muted-foreground"
              onClick={() => onOpenChange(false)}
              aria-label={tc("actions.close")}
            >
              <X className="size-5" />
            </Button>
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="hidden size-8 items-center justify-center rounded-lg bg-primary/10 text-primary sm:flex">
                <HelpCircle className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {question ? t("questions.edit") : t("questions.add")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {bank?.name}
                  {bank?.grade_level_name ? ` · ${bank.grade_level_name}` : ""}
                </p>
              </div>
            </div>

            <div className="ml-auto flex items-center gap-2">
              {type === "group" && subs.length > 0 && (
                <Button
                  variant="outline"
                  size="icon"
                  className="size-10"
                  title={t("exams.preview")}
                  aria-label={t("exams.preview")}
                  onClick={() => setGroupPreviewOpen(true)}
                >
                  <Eye className="size-4" />
                </Button>
              )}
              {/* The split save button sits in its OWN flex container — a
                  gap between the halves opens a seam. */}
              <div className="flex items-center">
              <Button
                className="h-10 rounded-r-none px-4 md:px-5"
                disabled={saving || imgUploading}
                onClick={() => save()}
              >
                {saving && <Loader2 className="size-4 animate-spin" />}
                {status === "draft" ? t("questions.saveDraft") : t("questions.saveAndPublish")}
              </Button>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    className="h-10 rounded-l-none border-l border-primary-foreground/25 px-2"
                    loading={saving || imgUploading}
                    aria-label={t("questions.saveOptions")}
                  >
                    <ChevronDown className="size-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                  <DropdownMenuItem onClick={() => save("published")}>
                    <Check className="size-4" /> {t("questions.saveAndPublish")}
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => save("draft")}>
                    {t("questions.saveDraft")}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
              </div>
            </div>
          </header>

          {/* ── Body: canvas + settings rail ─────────────────────────── */}
          <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8">
                {/* The question */}
                {parent !== null && (
                  <div className="rounded-2xl border border-primary/20 bg-primary/[0.04] px-4 py-3 text-sm">
                    <p className="mb-0.5 text-xs font-semibold uppercase tracking-wide text-primary">
                      {t("questions.subQuestionOf")}
                    </p>
                    <p className="line-clamp-2 text-muted-foreground">{stripHtml(parent.body.stem)}</p>
                  </div>
                )}

                {/* Mobile setup card — the desktop rail carries the same
                    controls on md+. */}
                <section className="space-y-5 rounded-2xl border bg-card p-4 shadow-xs md:hidden">
                  {typeSetup}
                </section>

                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <Label className="mb-2 block">
                    {type === "group" ? t("questions.passageStem") : t("questions.stem")}{" "}
                    <span className="text-destructive">*</span>
                  </Label>
                  <RichTextEditor
                    value={stem}
                    onChange={setStem}
                    placeholder={t("questions.stemPlaceholder")}
                    onUploadingChange={trackUploading}
                    onUploadImage={
                      bank
                        ? async (file) => {
                            const stored = await uploadMedia(file)
                            return { url: stored.url, path: stored.path }
                          }
                        : undefined
                    }
                  />
                  {errors.stem && <p className="mt-2 text-xs text-destructive">{errors.stem}</p>}
                </section>

                {/* The answer */}
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <div className="mb-3 flex items-center justify-between gap-3">
                    <Label>
                      {type === "group" ? t("questions.passageQuestions") : t("questions.answerArea")}
                    </Label>
                    <Badge variant="secondary">{t(`questions.types.${type}`)}</Badge>
                  </div>

                  {/* The passage's questions — created and managed right
                      here; on every exam they travel with the passage. */}
                  {type === "group" && (
                    <div className="space-y-3">
                      {subs.length === 0 ? (
                        <p className="rounded-xl border border-dashed px-4 py-5 text-center text-sm text-muted-foreground">
                          {t("questions.groupEmpty")}
                        </p>
                      ) : (
                        <div className="divide-y rounded-xl border">
                          {subs.map((sub, index) => (
                            <div key={sub.id} className="flex items-center gap-3 px-3 py-2.5">
                              <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                                {index + 1}
                              </span>
                              <div className="min-w-0 flex-1">
                                <p className="truncate text-sm">{stemText(sub.body.stem)}</p>
                                <p className="text-xs text-muted-foreground">
                                  {t(`questions.types.${sub.type}`)} · {Number(sub.points)}{" "}
                                  {t("questions.points").toLowerCase()}
                                  {sub.status === "draft" && (
                                    <span className="text-warning"> · {t("questions.draft")}</span>
                                  )}
                                </p>
                              </div>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0 text-muted-foreground"
                                title={t("questions.moveUp")}
                                aria-label={t("questions.moveUp")}
                                disabled={index === 0}
                                onClick={() => moveSub(index, -1)}
                              >
                                <ArrowUp className="size-4" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0 text-muted-foreground"
                                title={t("questions.moveDown")}
                                aria-label={t("questions.moveDown")}
                                disabled={index === subs.length - 1}
                                onClick={() => moveSub(index, 1)}
                              >
                                <ArrowDown className="size-4" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0 text-muted-foreground"
                                title={tc("actions.edit")}
                                aria-label={tc("actions.edit")}
                                onClick={() => {
                                  setChildEditing(sub)
                                  setChildOpen(true)
                                }}
                              >
                                <Pencil className="size-4" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                title={tc("actions.delete")}
                                aria-label={tc("actions.delete")}
                                onClick={() => confirmDelete(() => deleteChild(sub))}
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            </div>
                          ))}
                        </div>
                      )}
                      <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="min-w-0 flex-1 text-xs text-muted-foreground">{t("questions.groupHint")}</p>
                        <div className="flex items-center gap-2">
                          {subs.length > 1 && (
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={groupSubsByType}
                            >
                              <Sparkles className="size-3.5" /> {t("exams.groupByType")}
                            </Button>
                          )}
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            loading={saving}
                            disabled={imgUploading}
                            onClick={() => void addSubQuestion()}
                          >
                            <Plus className="size-3.5" /> {t("questions.addSub")}
                          </Button>
                        </div>
                      </div>
                    </div>
                  )}

                  {(type === "mcq_single" || type === "mcq_multi") && (
                    <div className="space-y-2">
                      {options.map((option, index) => {
                        const correct =
                          type === "mcq_single"
                            ? correctSingle === option.id
                            : correctMulti.includes(option.id)
                        return (
                          <div
                            key={option.id}
                            className={cn(
                              "flex items-center gap-2.5 rounded-xl border px-3 py-2 transition-colors",
                              correct && "border-success/40 bg-success/5",
                            )}
                          >
                            <button
                              type="button"
                              role={type === "mcq_single" ? "radio" : "checkbox"}
                              aria-checked={correct}
                              aria-label={t("questions.correct")}
                              onClick={() => {
                                if (type === "mcq_single") setCorrectSingle(option.id)
                                else
                                  setCorrectMulti((prev) =>
                                    prev.includes(option.id)
                                      ? prev.filter((id) => id !== option.id)
                                      : [...prev, option.id],
                                  )
                              }}
                              className={cn(
                                "flex size-6 shrink-0 items-center justify-center border transition-colors",
                                type === "mcq_single" ? "rounded-full" : "rounded-md",
                                correct
                                  ? "border-success bg-success text-white"
                                  : "border-input text-transparent hover:border-success/50",
                              )}
                            >
                              <Check className="size-3.5" />
                            </button>
                            <span className="w-5 text-center text-sm font-semibold uppercase text-muted-foreground">
                              {option.id}
                            </span>
                            <RichTextEditor
                              value={option.text}
                              onChange={(html) => setOption(index, html)}
                              placeholder={t("questions.optionPlaceholder")}
                              compact
                              className="min-w-0 flex-1 border-0 bg-transparent shadow-none focus-within:ring-0"
                              onUploadingChange={trackUploading}
                              onUploadImage={
                                bank
                                  ? async (file) => {
                                      const stored = await uploadMedia(file)
                                      return { url: stored.url, path: stored.path }
                                    }
                                  : undefined
                              }
                            />
                            {options.length > 2 && (
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 text-muted-foreground hover:text-destructive"
                                aria-label={tc("actions.delete")}
                                onClick={() => {
                                  setOptions((prev) => prev.filter((_, i) => i !== index))
                                  setCorrectMulti((prev) => prev.filter((id) => id !== option.id))
                                  if (correctSingle === option.id) setCorrectSingle("")
                                }}
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            )}
                          </div>
                        )
                      })}
                      <div className="flex items-center justify-between gap-3 pt-1">
                        <p className="text-xs text-muted-foreground">
                          {type === "mcq_single"
                            ? t("questions.markCorrectHint")
                            : t("questions.markCorrectMultiHint")}
                        </p>
                        {options.length < OPTION_IDS.length && (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                              setOptions((prev) => [...prev, { id: nextOptionId(prev), text: "" }])
                            }
                          >
                            <Plus className="size-3.5" /> {t("questions.addOption")}
                          </Button>
                        )}
                      </div>
                      {errors.options && <p className="text-xs text-destructive">{errors.options}</p>}
                    </div>
                  )}

                  {type === "true_false" && (
                    <div className="space-y-2">
                      <p className="text-sm text-muted-foreground">{t("questions.trueFalseCorrect")}</p>
                      <div className="grid grid-cols-2 gap-2">
                        {[true, false].map((val) => (
                          <button
                            key={String(val)}
                            type="button"
                            onClick={() => setTfCorrect(val)}
                            className={cn(
                              "h-11 rounded-xl border text-sm font-medium transition-colors",
                              tfCorrect === val
                                ? "border-success/50 bg-success/10 text-success"
                                : "hover:bg-muted/60",
                            )}
                          >
                            {val ? t("questions.true") : t("questions.false")}
                          </button>
                        ))}
                      </div>
                    </div>
                  )}

                  {type === "short_answer" && (
                    <div className="space-y-2">
                      <Label className="text-muted-foreground">{t("questions.accepted")}</Label>
                      <Textarea rows={3} value={accepted} onChange={(e) => setAccepted(e.target.value)} />
                      <p className="text-xs text-muted-foreground">{t("questions.acceptedHint")}</p>
                    </div>
                  )}

                  {type === "numeric" && (
                    <div className="grid grid-cols-2 gap-3">
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">
                          {t("questions.numericValue")} <span className="text-destructive">*</span>
                        </Label>
                        <Input
                          type="number"
                          step="any"
                          className="no-spinner"
                          value={numericValue}
                          onChange={(e) => setNumericValue(e.target.value)}
                        />
                        {errors.numeric && <p className="text-xs text-destructive">{errors.numeric}</p>}
                      </div>
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("questions.tolerance")}</Label>
                        <Input
                          type="number"
                          step="any"
                          min="0"
                          className="no-spinner"
                          value={tolerance}
                          onChange={(e) => setTolerance(e.target.value)}
                        />
                      </div>
                    </div>
                  )}

                  {type === "fill_blank" && (
                    <div className="space-y-2">
                      {blanks.map((block, index) => (
                        <div key={index} className="flex items-start gap-2">
                          <span className="mt-2.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                            {index + 1}
                          </span>
                          <Textarea
                            rows={2}
                            value={block}
                            onChange={(e) =>
                              setBlanks((prev) => prev.map((b, i) => (i === index ? e.target.value : b)))
                            }
                            className="flex-1"
                          />
                          {blanks.length > 1 && (
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="mt-1 text-muted-foreground"
                              aria-label={tc("actions.delete")}
                              onClick={() => setBlanks((prev) => prev.filter((_, i) => i !== index))}
                            >
                              <Trash2 className="size-4" />
                            </Button>
                          )}
                        </div>
                      ))}
                      <div className="flex items-center justify-between gap-3">
                        <p className="text-xs text-muted-foreground">{t("questions.blankHint")}</p>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => setBlanks((prev) => [...prev, ""])}
                        >
                          <Plus className="size-3.5" /> {t("questions.addBlank")}
                        </Button>
                      </div>
                      {errors.blanks && <p className="text-xs text-destructive">{errors.blanks}</p>}
                    </div>
                  )}

                  {type === "matching" && (
                    <div className="space-y-4">
                      <div className="grid gap-4 sm:grid-cols-2">
                        {(
                          [
                            { label: t("questions.left"), items: left, set: setLeft, prefix: "l" },
                            { label: t("questions.right"), items: right, set: setRight, prefix: "r" },
                          ] as const
                        ).map(({ label, items, set, prefix }) => (
                          <div key={prefix} className="space-y-2">
                            <Label className="text-muted-foreground">{label}</Label>
                            {items.map((item, index) => (
                              <Input
                                key={item.id}
                                value={item.text}
                                onChange={(e) =>
                                  set((prev) =>
                                    prev.map((o, i) => (i === index ? { ...o, text: e.target.value } : o)),
                                  )
                                }
                              />
                            ))}
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() =>
                                set((prev) => [...prev, { id: `${prefix}${prev.length + 1}`, text: "" }])
                              }
                            >
                              <Plus className="size-3.5" /> {t("questions.addItem")}
                            </Button>
                          </div>
                        ))}
                      </div>
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("questions.matchWith")}</Label>
                        {left
                          .filter((item) => item.text.trim() !== "")
                          .map((item) => (
                            <div key={item.id} className="flex items-center gap-2">
                              <span className="min-w-0 flex-1 truncate text-sm">{item.text}</span>
                              <Select
                                value={pairs[item.id] ?? undefined}
                                onValueChange={(v) => setPairs((prev) => ({ ...prev, [item.id]: v }))}
                              >
                                <SelectTrigger className="w-44">
                                  <SelectValue placeholder="→" />
                                </SelectTrigger>
                                <SelectContent>
                                  {right
                                    .filter((o) => o.text.trim() !== "")
                                    .map((o) => (
                                      <SelectItem key={o.id} value={o.id}>
                                        {o.text}
                                      </SelectItem>
                                    ))}
                                </SelectContent>
                              </Select>
                            </div>
                          ))}
                        {errors.matching && <p className="text-xs text-destructive">{errors.matching}</p>}
                      </div>
                    </div>
                  )}

                  {type === "essay" && (
                    <div className="space-y-2">
                      <Label className="text-muted-foreground">{t("questions.rubric")}</Label>
                      <Textarea rows={3} value={rubric} onChange={(e) => setRubric(e.target.value)} />
                    </div>
                  )}
                </section>

                {/* Attach */}
                <section
                  {...attachmentDrop.dropProps}
                  className={cn(
                    "rounded-2xl border bg-card p-4 shadow-xs md:p-5",
                    attachmentDrop.dragOver && DROP_ACTIVE,
                  )}
                >
                  <Label className="mb-3 block">{t("questions.attach")}</Label>
                  <QuestionAttachments
                    attachments={attachments}
                    onRemove={(index) => setAttachments((prev) => prev.filter((_, i) => i !== index))}
                    onRename={(index, name) =>
                      setAttachments((prev) => prev.map((a, i) => (i === index ? { ...a, name } : a)))
                    }
                    className="mb-3"
                  />
                  <div className="flex flex-wrap gap-2">
                    {(
                      [
                        { kind: "link", icon: <Link2 className="size-4" />, label: t("questions.attachLink") },
                        { kind: "youtube", icon: <CirclePlay className="size-4" />, label: t("questions.attachVideo") },
                      ] as const
                    ).map(({ kind, icon, label }) => (
                      <Popover
                        key={kind}
                        open={linkDraft?.kind === kind}
                        onOpenChange={(o) =>
                          setLinkDraft(o ? { kind, url: "", name: "" } : null)
                        }
                      >
                        <PopoverTrigger asChild>
                          <Button type="button" variant="outline" size="sm" className="h-9 rounded-full">
                            {icon} {label}
                          </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-80 space-y-3" align="start">
                          <div className="space-y-1.5">
                            <Label className="text-xs">{t("questions.attachUrl")}</Label>
                            <Input
                              autoFocus
                              inputMode="url"
                              placeholder="https://…"
                              value={linkDraft?.url ?? ""}
                              onChange={(e) =>
                                setLinkDraft((prev) => (prev ? { ...prev, url: e.target.value } : prev))
                              }
                              onKeyDown={(e) => e.key === "Enter" && addLinkAttachment()}
                            />
                          </div>
                          <div className="space-y-1.5">
                            <Label className="text-xs">{t("questions.attachName")}</Label>
                            <Input
                              value={linkDraft?.name ?? ""}
                              onChange={(e) =>
                                setLinkDraft((prev) => (prev ? { ...prev, name: e.target.value } : prev))
                              }
                              onKeyDown={(e) => e.key === "Enter" && addLinkAttachment()}
                            />
                          </div>
                          <Button
                            type="button"
                            size="sm"
                            className="w-full"
                            disabled={!/^https?:\/\//i.test(linkDraft?.url.trim() ?? "")}
                            onClick={addLinkAttachment}
                          >
                            {tc("actions.add")}
                          </Button>
                        </PopoverContent>
                      </Popover>
                    ))}
                    <label
                      className={cn(
                        "inline-flex h-9 cursor-pointer items-center gap-2 rounded-full border px-3 text-sm font-medium transition-colors hover:bg-muted/60",
                        uploadingFile && "pointer-events-none opacity-60",
                      )}
                    >
                      {uploadingFile ? (
                        <Loader2 className="size-4 animate-spin" />
                      ) : (
                        <FileUp className="size-4" />
                      )}
                      {t("questions.attachFile")}
                      <input
                        type="file"
                        className="hidden"
                        accept={QUESTION_FILE_ACCEPT}
                        onChange={(e) => {
                          attachmentDrop.takeFiles(e.target.files)
                          e.target.value = ""
                        }}
                      />
                    </label>
                    <DropHint className="self-center" />
                  </div>
                  <p className="mt-2 text-xs text-muted-foreground">{t("questions.attachHint")}</p>
                </section>
              </div>
            </main>

            {/* Settings rail */}
            <aside className="border-t bg-background md:min-h-0 md:w-[340px] md:shrink-0 md:overflow-y-auto md:border-l md:border-t-0">
              <div className="space-y-5 p-4 md:p-5">
                <div className="hidden space-y-5 md:block">{typeSetup}</div>

                <div className="space-y-2">
                  <Label>{t("questions.topic")}</Label>
                  <Input
                    value={topic}
                    onChange={(e) => setTopic(e.target.value)}
                    placeholder={t("questions.topicPlaceholder")}
                  />
                  {bankTopics.length > 0 && (
                    <div className="flex flex-wrap gap-1.5 pt-0.5">
                      {bankTopics.map((chip) => (
                        <button
                          key={chip}
                          type="button"
                          onClick={() => setTopic(chip)}
                          className={cn(
                            "rounded-full border px-2.5 py-1 text-xs transition-colors",
                            topic.toLowerCase() === chip.toLowerCase()
                              ? "border-primary/40 bg-primary/10 text-primary"
                              : "text-muted-foreground hover:bg-muted/60",
                          )}
                        >
                          {chip}
                        </button>
                      ))}
                    </div>
                  )}
                  <p className="text-xs text-muted-foreground">{t("questions.topicHint")}</p>
                </div>

                <div className="space-y-2">
                  <Label>{t("questions.status")}</Label>
                  <Select value={status} onValueChange={(v) => setStatus(v as typeof status)}>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="published">{t("questions.published")}</SelectItem>
                      <SelectItem value="draft">{t("questions.draft")}</SelectItem>
                      {question?.status === "retired" && (
                        <SelectItem value="retired">{t("questions.retired")}</SelectItem>
                      )}
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-muted-foreground">{t("questions.statusHint")}</p>
                </div>

                <div className="space-y-2">
                  <Label>{t("questions.explanation")}</Label>
                  <Textarea
                    rows={3}
                    value={explanation}
                    onChange={(e) => setExplanation(e.target.value)}
                  />
                  <p className="text-xs text-muted-foreground">{t("questions.explanationHint")}</p>
                </div>

                <div className="grid grid-cols-1 gap-3">
                  <div className="space-y-2">
                    <Label>{t("questions.source")}</Label>
                    <Input
                      value={source}
                      onChange={(e) => setSource(e.target.value)}
                      placeholder={t("questions.sourcePlaceholder")}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("questions.tags")}</Label>
                    <Input
                      value={tags}
                      onChange={(e) => setTags(e.target.value)}
                      placeholder={t("questions.tagsPlaceholder")}
                    />
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>

    {confirmDialog}

    {/* The sub-question editor stacks on top of the passage; a sub-question
        can never itself be a group, so the nesting stops at one level. */}
    {!insideGroup && type === "group" && effectiveQuestion !== null && (
      <QuestionEditor
        bank={bank}
        question={childEditing}
        parent={effectiveQuestion}
        open={childOpen}
        onOpenChange={(o) => {
          setChildOpen(o)
          if (!o) setChildEditing(null)
        }}
        onSaved={handleChildSaved}
      />
    )}

    {!insideGroup && groupPreviewQuiz !== null && (
      <ExamPreview
        quiz={groupPreviewQuiz}
        open={groupPreviewOpen}
        onOpenChange={setGroupPreviewOpen}
      />
    )}
    </>
  )
}
