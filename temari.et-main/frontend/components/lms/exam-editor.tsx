"use client"

import {
  AlertTriangle,
  Check,
  ChevronDown,
  ChevronUp,
  CornerDownRight,
  Eye,
  FileText,
  FolderInput,
  GripVertical,
  Layers,
  Loader2,
  Plus,
  RefreshCw,
  Sparkles,
  Trash2,
  Users,
  X,
} from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { ExamPreview } from "@/components/lms/exam-preview"
import { stemText, TYPE_GROUP_ORDER } from "@/components/lms/question-content"
import { QuestionEditor } from "@/components/lms/question-editor"
import { DateTimeField, romanNumeral, useClassOptions } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { RichTextEditor } from "@/components/ui/rich-text"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { ApiError, apiFetch } from "@/lib/api"
import { addisToday } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { Textarea } from "@/components/ui/textarea"
import type {
  GradeLevel,
  Question,
  QuestionBank,
  QuestionGroupStem,
  QuestionType,
  Quiz,
  QuizDetail,
  QuizDrawRule,
  QuizKind,
  Subject,
} from "@/lib/types"
import { cn } from "@/lib/utils"

/** Real-paper section order for the one-click "group by type" action. */
interface PickedEntry {
  question: Question
  points: string
  part: number | null
}

interface GradebookSlot {
  id: number
  name: string
  max_score?: number | string
}

interface Props {
  quiz: QuizDetail | null
  platform?: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: (quiz: Quiz) => void
}

/**
 * The full-screen exam studio (ADR-016). Center: who takes it (grade →
 * subject → sections), the exam itself (title + rich instructions) and the
 * paper (fixed picks or random draw). Right rail: timing, integrity and
 * grading settings. Saving is a split button: publish now, or keep a draft.
 */
export function ExamEditor({ quiz, platform = false, open, onOpenChange, onSaved }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const { classes } = useClassOptions()

  const [kind, setKind] = useState<QuizKind>(platform ? "mock" : "quiz")
  const [title, setTitle] = useState("")
  const [instructions, setInstructions] = useState("")
  const [gradeName, setGradeName] = useState("")
  const [subjectKey, setSubjectKey] = useState("")
  const [targetIds, setTargetIds] = useState<number[]>([])
  const [subjectId, setSubjectId] = useState<string>("")
  const [gradeLevelId, setGradeLevelId] = useState<string>("")
  const [examKind, setExamKind] = useState<string>("")
  const [examYearEc, setExamYearEc] = useState("")
  const [stream, setStream] = useState<string>("")
  const [language, setLanguage] = useState("en")

  // settings
  const [duration, setDuration] = useState("")
  const [opensAt, setOpensAt] = useState<string | null>(null)
  const [closesAt, setClosesAt] = useState<string | null>(null)
  const [attemptsAllowed, setAttemptsAllowed] = useState("1")
  const [shuffleQuestions, setShuffleQuestions] = useState(true)
  const [shuffleOptions, setShuffleOptions] = useState(true)
  const [navigation, setNavigation] = useState<"free" | "sequential">("free")
  const [resultsPolicy, setResultsPolicy] = useState("immediately")
  const [revealAnswers, setRevealAnswers] = useState(false)
  const [gradeAttempt, setGradeAttempt] = useState("best")
  const [accessCode, setAccessCode] = useState("")

  // paper
  const [mode, setMode] = useState<"fixed" | "draw">("fixed")
  const [banks, setBanks] = useState<QuestionBank[]>([])
  const [banksLoading, setBanksLoading] = useState(false)
  const [banksError, setBanksError] = useState(false)
  const [banksRetry, setBanksRetry] = useState(0)
  const [selectedBankIds, setSelectedBankIds] = useState<number[]>([])
  const [poolQuestions, setPoolQuestions] = useState<Question[]>([])
  const [poolLoading, setPoolLoading] = useState(false)
  const [poolQuery, setPoolQuery] = useState("")
  const [picked, setPicked] = useState<Map<number, PickedEntry>>(new Map())
  const [parts, setParts] = useState<{ title: string; instructions: string }[]>([])
  const [draw, setDraw] = useState<QuizDrawRule[]>([])
  const [newQuestionOpen, setNewQuestionOpen] = useState(false)
  const [newQuestionBankId, setNewQuestionBankId] = useState<number | null>(null)

  // gradebook link (anchor class = first target)
  const [slots, setSlots] = useState<GradebookSlot[]>([])
  const [assessmentId, setAssessmentId] = useState<string>("")

  const [subjects, setSubjects] = useState<Subject[]>([])
  const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([])
  const [imgUploading, setImgUploading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)

  // In-studio dry run: fixed papers preview straight from the working state
  // (before OR after publishing); draw papers sample from the server.
  const [previewOpen, setPreviewOpen] = useState(false)
  const [previewSample, setPreviewSample] = useState<QuizDetail | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)

  const structureLocked = quiz !== null && (quiz.attempts_count ?? 0) > 0

  // ── grade → subject → sections cascade over the caller's classes ──
  const gradeNames = useMemo(
    () => [...new Set(classes.map((c) => c.section.grade_level).filter((g): g is string => Boolean(g)))],
    [classes],
  )
  const subjectChoices = useMemo(() => {
    const seen = new Map<string, string>()
    for (const option of classes) {
      if (option.section.grade_level === gradeName && option.subject.id) {
        seen.set(String(option.subject.id), option.subject.name ?? "")
      }
    }
    return [...seen.entries()].map(([id, name]) => ({ id, name }))
  }, [classes, gradeName])
  const sectionChoices = useMemo(
    () =>
      classes.filter(
        (option) =>
          option.section.grade_level === gradeName && String(option.subject.id) === subjectKey,
      ),
    [classes, gradeName, subjectKey],
  )

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- sync editor with the edited row */
    setErrors({})
    setKind(quiz?.kind ?? (platform ? "mock" : "quiz"))
    setTitle(quiz?.title ?? "")
    setInstructions(quiz?.instructions ?? "")
    setTargetIds(quiz?.subject_assignment_ids ?? (quiz?.subject_assignment_id ? [quiz.subject_assignment_id] : []))
    setGradeName("")
    setSubjectKey("")
    setSubjectId(quiz?.subject_id ? String(quiz.subject_id) : "")
    setGradeLevelId(quiz?.grade_level_id ? String(quiz.grade_level_id) : "")
    setExamKind(quiz?.exam_kind ?? "")
    setExamYearEc(quiz?.exam_year_ec ? String(quiz.exam_year_ec) : "")
    setStream(quiz?.stream ?? "")
    setLanguage(quiz?.language ?? "en")
    const settings = quiz?.settings ?? {}
    setDuration(settings.duration_minutes ? String(settings.duration_minutes) : "")
    setOpensAt(settings.opens_at ?? null)
    setClosesAt(settings.closes_at ?? null)
    setAttemptsAllowed(String(settings.attempts_allowed ?? 1))
    setShuffleQuestions(settings.shuffle_questions ?? true)
    setShuffleOptions(settings.shuffle_options ?? true)
    setNavigation(settings.navigation ?? "free")
    setResultsPolicy(settings.results_policy ?? "immediately")
    setRevealAnswers(settings.reveal_answers ?? false)
    setGradeAttempt(settings.grade_attempt ?? "best")
    setAccessCode("")
    setMode(quiz?.draw?.length ? "draw" : "fixed")
    setDraw(quiz?.draw ?? [])
    setParts((quiz?.parts ?? []).map((part) => ({ title: part.title, instructions: part.instructions ?? "" })))
    setPicked(
      new Map(
        (quiz?.questions ?? []).map((question) => [
          question.id,
          { question, points: String(question.quiz_points), part: question.part_index ?? null },
        ]),
      ),
    )
    setAssessmentId(quiz?.assessment_id ? String(quiz.assessment_id) : "")
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [open, quiz, platform])

  // When editing, resolve the grade/subject pickers from the saved targets.
  useEffect(() => {
    if (!open || platform || targetIds.length === 0 || classes.length === 0 || gradeName !== "") return
    const anchor = classes.find((option) => targetIds.includes(option.subject_assignment_id))
    if (anchor) {
      /* eslint-disable react-hooks/set-state-in-effect -- one-shot resolve from loaded data */
      setGradeName(anchor.section.grade_level ?? "")
      setSubjectKey(String(anchor.subject.id ?? ""))
      /* eslint-enable react-hooks/set-state-in-effect */
    }
  }, [open, platform, targetIds, classes, gradeName])

  // Close the inline question studio when the exam studio itself closes.
  useEffect(() => {
    if (!open) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset nested modal when the parent closes
      setNewQuestionOpen(false)
    }
  }, [open])

  // Banks the paper may draw from. A failed fetch flags banksError so the
  // "New question" button can explain itself and offer a retry instead of
  // silently sitting disabled (bumping banksRetry re-runs this effect).
  useEffect(() => {
    if (!open) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- fetch-status flags must flip when the request starts
    setBanksLoading(true)
    setBanksError(false)
    apiFetch<{ data: QuestionBank[] }>(`/question-banks?per_page=100${platform ? "&platform=1" : ""}`)
      .then((res) => !cancelled && setBanks(res.data))
      .catch(() => {
        if (cancelled) return
        setBanks([])
        setBanksError(true)
      })
      .finally(() => !cancelled && setBanksLoading(false))
    if (platform) {
      apiFetch<{ data: Subject[] }>("/catalogs/subjects?per_page=100")
        .then((res) => !cancelled && setSubjects(res.data))
        .catch(() => !cancelled && setSubjects([]))
      apiFetch<{ data: GradeLevel[] }>("/grade-levels")
        .then((res) => !cancelled && setGradeLevels(res.data))
        .catch(() => !cancelled && setGradeLevels([]))
    }
    return () => {
      cancelled = true
    }
  }, [open, platform, banksRetry])

  // Gradebook slots of the ANCHOR class (first selected section).
  const anchorId = targetIds[0] ?? null
  useEffect(() => {
    if (!open || platform || !anchorId) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset when the class clears
      setSlots([])
      return
    }
    let cancelled = false
    apiFetch<{ data: GradebookSlot[] }>(`/subject-assignments/${anchorId}/assessments`)
      .then((res) => !cancelled && setSlots(res.data))
      .catch(() => !cancelled && setSlots([]))
    return () => {
      cancelled = true
    }
  }, [open, platform, anchorId])

  // Questions across every bank currently browsed — one request no matter
  // how many banks are selected (backend batches via ?question_bank_id[]=).
  useEffect(() => {
    if (!open || selectedBankIds.length === 0) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset when no bank is selected
      setPoolQuestions([])
      return
    }
    let cancelled = false
    setPoolLoading(true)
    const params = new URLSearchParams({ status: "published", per_page: "200" })
    selectedBankIds.forEach((id) => params.append("question_bank_id[]", String(id)))
    apiFetch<{ data: Question[] }>(`/questions?${params.toString()}`)
      .then((res) => !cancelled && setPoolQuestions(res.data))
      .catch(() => !cancelled && setPoolQuestions([]))
      .finally(() => !cancelled && setPoolLoading(false))
    return () => {
      cancelled = true
    }
  }, [open, selectedBankIds])

  const totalPoints = useMemo(
    () => [...picked.values()].reduce((sum, entry) => sum + Number(entry.points || 0), 0),
    [picked],
  )

  // Draft questions on the paper can't go live — the exam refuses to publish
  // until they're published in their bank. Surface the count so a full-looking
  // paper never fails to publish for a reason the teacher can't see.
  const draftPickedCount = useMemo(
    () => [...picked.values()].filter((entry) => entry.question.status === "draft").length,
    [picked],
  )

  // Only banks the caller may actually add questions to (their own, or a
  // supervisory role) are offered as a target for inline question creation.
  const writableBanks = useMemo(() => banks.filter((bank) => bank.can_edit), [banks])

  // A group's sub-questions, in authored order (children ride the same pool
  // request as their container — same bank).
  const childrenByParent = useMemo(() => {
    const map = new Map<number, Question[]>()
    for (const question of poolQuestions) {
      if (question.parent_id) {
        map.set(question.parent_id, [...(map.get(question.parent_id) ?? []), question])
      }
    }
    map.forEach((list) =>
      list.sort((a, b) => (a.position ?? 0) - (b.position ?? 0) || a.id - b.id),
    )
    return map
  }, [poolQuestions])

  // Picking a GROUP picks its published sub-questions as one block — the
  // container itself never becomes a paper entry.
  const togglePick = useCallback(
    (question: Question, checked: boolean) => {
      const members =
        question.type === "group"
          ? (childrenByParent.get(question.id) ?? []).filter((child) => child.status === "published")
          : [question]
      if (members.length === 0) return
      setPicked((prev) => {
        const next = new Map(prev)
        for (const member of members) {
          if (checked) {
            if (!next.has(member.id)) {
              next.set(member.id, { question: member, points: String(member.points), part: null })
            }
          } else {
            next.delete(member.id)
          }
        }
        return next
      })
    },
    [childrenByParent],
  )

  // Fixed papers are ordered by pick order (a Map preserves insertion order);
  // rendering filters that order per part, so moving within the map moves
  // within the part. Nudge = swap with the nearest neighbor IN THE SAME part.
  const movePicked = useCallback((questionId: number, direction: -1 | 1) => {
    setPicked((prev) => {
      const entries = [...prev.entries()]
      const index = entries.findIndex(([id]) => id === questionId)
      if (index === -1) return prev
      const part = entries[index][1].part
      let swapIndex = index + direction
      while (swapIndex >= 0 && swapIndex < entries.length && entries[swapIndex][1].part !== part) {
        swapIndex += direction
      }
      if (swapIndex < 0 || swapIndex >= entries.length) return prev
      ;[entries[index], entries[swapIndex]] = [entries[swapIndex], entries[index]]
      return new Map(entries)
    })
  }, [])

  // Drag-and-drop over the paper: dropping on a question moves the dragged
  // one next to it (adopting its part); dropping on a part bucket appends.
  const [dragId, setDragId] = useState<number | null>(null)
  const [dragOverId, setDragOverId] = useState<number | null>(null)
  const [dragOverPart, setDragOverPart] = useState<number | null | "none" | "idle">("idle")

  const reorderPicked = useCallback((sourceId: number, targetId: number) => {
    setPicked((prev) => {
      if (sourceId === targetId) return prev
      const target = prev.get(targetId)
      const entries = [...prev.entries()]
      const from = entries.findIndex(([id]) => id === sourceId)
      if (from === -1 || target === undefined) return prev
      const [item] = entries.splice(from, 1)
      item[1] = { ...item[1], part: target.part }
      const to = entries.findIndex(([id]) => id === targetId)
      entries.splice(to, 0, item)
      return new Map(entries)
    })
  }, [])

  /** File a question under a part (or none) — appends to that bucket. */
  const setQuestionPart = useCallback((questionId: number, part: number | null) => {
    setPicked((prev) => {
      const entry = prev.get(questionId)
      if (!entry || entry.part === part) return prev
      const next = new Map(prev)
      next.delete(questionId)
      next.set(questionId, { ...entry, part })
      return next
    })
  }, [])

  const addPart = useCallback(() => {
    setParts((prev) => [...prev, { title: "", instructions: "" }])
  }, [])

  const removePart = useCallback((index: number) => {
    setParts((prev) => prev.filter((_, i) => i !== index))
    // Questions of the removed part fall back to unassigned; later parts
    // shift down by one so their questions follow them.
    setPicked((prev) => {
      const next = new Map<number, PickedEntry>()
      prev.forEach((entry, id) => {
        const part =
          entry.part === null || entry.part < index
            ? entry.part
            : entry.part === index
              ? null
              : entry.part - 1
        next.set(id, { ...entry, part })
      })
      return next
    })
  }, [])

  const movePart = useCallback((index: number, direction: -1 | 1) => {
    const swap = index + direction
    setParts((prev) => {
      if (swap < 0 || swap >= prev.length) return prev
      const next = [...prev]
      ;[next[index], next[swap]] = [next[swap], next[index]]
      return next
    })
    setPicked((prev) => {
      if (swap < 0) return prev
      const next = new Map<number, PickedEntry>()
      prev.forEach((entry, id) => {
        const part = entry.part === index ? swap : entry.part === swap ? index : entry.part
        next.set(id, { ...entry, part })
      })
      return next
    })
  }, [])

  // One click builds a real-world paper: a part per question type present,
  // in the customary order, with sensible default titles and instructions.
  // A passage group is ONE unit — the whole sibling block files under the
  // type of its first sub-question, never split across parts.
  const groupByType = useCallback(() => {
    const unitType = new Map<number, QuestionType>()
    picked.forEach((entry, id) => {
      const parentId = entry.question.parent_id
      if (parentId) {
        if (!unitType.has(parentId)) unitType.set(parentId, entry.question.type)
        unitType.set(-id, unitType.get(parentId)!)
      } else {
        unitType.set(-id, entry.question.type)
      }
    })
    const typeOf = (id: number, entry: PickedEntry): QuestionType =>
      unitType.get(-id) ?? entry.question.type
    const typesPresent = TYPE_GROUP_ORDER.filter((type) =>
      [...picked.entries()].some(([id, entry]) => typeOf(id, entry) === type),
    )
    if (typesPresent.length === 0) return
    setParts(
      typesPresent.map((type) => ({
        title: t(`exams.partTitles.${type}`),
        instructions: t(`exams.partHints.${type}`),
      })),
    )
    setPicked((prev) => {
      const next = new Map<number, PickedEntry>()
      for (const type of typesPresent) {
        prev.forEach((entry, id) => {
          if (typeOf(id, entry) === type) {
            next.set(id, { ...entry, part: typesPresent.indexOf(type) })
          }
        })
      }
      prev.forEach((entry, id) => {
        if (!next.has(id)) next.set(id, { ...entry, part: null })
      })
      return next
    })
  }, [picked, t])

  // A question created inline (without leaving the exam studio) joins the
  // browsed pool immediately and, on a fixed paper, is picked automatically.
  const handleQuestionCreated = useCallback(
    (question: Question) => {
      setPoolQuestions((prev) => (prev.some((q) => q.id === question.id) ? prev : [question, ...prev]))
      if (newQuestionBankId !== null) {
        setSelectedBankIds((prev) => (prev.includes(newQuestionBankId) ? prev : [...prev, newQuestionBankId]))
      }
      if (mode === "fixed") {
        setPicked((prev) => {
          const next = new Map(prev)
          next.set(question.id, { question, points: String(question.points), part: null })
          return next
        })
      }
    },
    [mode, newQuestionBankId],
  )

  // Already-picked questions stay visible even if their bank isn't browsed
  // right now (e.g. editing a quiz built from a bank not currently selected).
  const combinedRows = useMemo(() => {
    const byId = new Map<number, Question>()
    poolQuestions.forEach((question) => byId.set(question.id, question))
    picked.forEach((entry) => {
      if (!byId.has(entry.question.id)) byId.set(entry.question.id, entry.question)
    })
    return [...byId.values()]
  }, [poolQuestions, picked])

  // The paper in reading order: declared parts first (their questions in
  // pick order), then everything unassigned — matching the backend, the
  // player and the printed PDF.
  const paper = useMemo(() => {
    const list = [...picked.entries()].map(([id, entry]) => ({ id, ...entry }))
    const buckets: (typeof list)[] = parts.map(() => [])
    const none: typeof list = []
    for (const item of list) {
      if (item.part !== null && item.part < parts.length) buckets[item.part].push(item)
      else none.push(item)
    }
    return { buckets, none, ordered: [...buckets.flat(), ...none] }
  }, [picked, parts])

  const pickedOrder = useMemo(() => {
    const order = new Map<number, number>()
    paper.ordered.forEach((item, index) => order.set(item.id, index + 1))
    return order
  }, [paper])

  // The search box narrows EVERYTHING in the one list — the arranged paper
  // rows and the not-yet-picked bank pool below them.
  const matchingIds = useMemo(() => {
    const query = poolQuery.trim().toLowerCase()
    const matches = (question: Question) =>
      !query ||
      stemText(question.body.stem).toLowerCase().includes(query) ||
      (question.topic ?? "").toLowerCase().includes(query)

    return new Set(combinedRows.filter(matches).map((question) => question.id))
  }, [combinedRows, poolQuery])

  const unpickedRows = useMemo(
    () =>
      combinedRows.filter((question) => {
        if (picked.has(question.id) || !matchingIds.has(question.id)) return false
        // Sub-questions join through their group's row, never alone.
        if (question.parent_id && combinedRows.some((row) => row.id === question.parent_id)) {
          return false
        }
        if (question.type === "group") {
          const kids = (childrenByParent.get(question.id) ?? []).filter(
            (child) => child.status === "published",
          )
          return kids.length > 0 && kids.some((child) => !picked.has(child.id))
        }
        return true
      }),
    [combinedRows, picked, matchingIds, childrenByParent],
  )

  const clearError = useCallback((key: string) => {
    setErrors((prev) => {
      if (!(key in prev)) return prev
      const next = { ...prev }
      delete next[key]
      return next
    })
  }, [])

  async function uploadImage(file: File) {
    const form = new FormData()
    form.append("file", file)
    const res = await apiFetch<{ data: { url: string; path: string } }>("/lms/uploads", {
      method: "POST",
      body: form,
    })
    return res.data
  }

  function validate(): boolean {
    const found: Record<string, string[]> = {}
    if (title.trim() === "") found.title = [tc("validation.required")]
    if (!platform && targetIds.length === 0) {
      found.subject_assignment_ids = [t("exams.pickSections")]
    }
    if (opensAt && closesAt && new Date(closesAt) <= new Date(opensAt)) {
      found["settings.closes_at"] = [t("exams.closesAfterOpens")]
    }
    if (!structureLocked && mode === "draw") {
      if (draw.length === 0) {
        found.draw = [t("exams.drawNeedsRule")]
      } else if (draw.some((rule) => !rule.question_bank_id || !rule.count || rule.count < 1)) {
        found.draw = [t("exams.drawIncomplete")]
      }
    }
    if (!structureLocked && mode === "fixed" && parts.some((part) => part.title.trim() === "")) {
      found.parts = [t("exams.partTitleRequired")]
    }
    setErrors(found)
    return Object.keys(found).length === 0
  }

  async function save(action: "keep" | "publish" | "draft" = "keep") {
    if (!validate()) {
      toast.error(t("questions.errors.fix"))
      return
    }
    setSaving(true)
    setErrors({})
    try {
      const body: Record<string, unknown> = {
        kind,
        title,
        instructions: instructions || null,
        language,
        settings: {
          duration_minutes: duration ? Number(duration) : 0,
          opens_at: opensAt,
          closes_at: closesAt,
          attempts_allowed: Number(attemptsAllowed || 0),
          shuffle_questions: shuffleQuestions,
          shuffle_options: shuffleOptions,
          navigation,
          results_policy: resultsPolicy,
          reveal_answers: revealAnswers,
          grade_attempt: gradeAttempt,
        },
        assessment_id: assessmentId ? Number(assessmentId) : null,
      }
      if (platform) {
        body.platform = true
        body.subject_id = subjectId ? Number(subjectId) : null
        body.grade_level_id = gradeLevelId ? Number(gradeLevelId) : null
        body.exam_kind = examKind || null
        body.exam_year_ec = examYearEc ? Number(examYearEc) : null
        body.stream = stream || null
      } else {
        body.subject_assignment_ids = targetIds
      }
      if (accessCode.trim() !== "") body.access_code = accessCode.trim()
      if (!structureLocked) {
        if (mode === "fixed") {
          body.questions = paper.ordered.map((entry) => ({
            question_id: entry.question.id,
            points: Number(entry.points || entry.question.points),
            part: entry.part,
          }))
          body.parts = parts.length
            ? parts.map((part) => ({
                title: part.title.trim(),
                instructions: part.instructions.trim() || null,
              }))
            : null
          body.draw = null
        } else {
          body.draw = draw
          body.parts = null
        }
      }

      const res = await apiFetch<{ data: Quiz }>(quiz ? `/quizzes/${quiz.id}` : "/quizzes", {
        method: quiz ? "PUT" : "POST",
        body,
      })

      let saved = res.data
      if (action === "publish" && saved.status === "draft") {
        const published = await apiFetch<{ data: Quiz }>(`/quizzes/${saved.id}/publish`, {
          method: "POST",
          body: {},
        })
        saved = published.data
      }

      toast.success(action === "publish" ? t("exams.published") : t("exams.saved"))
      onOpenChange(false)
      onSaved(saved)
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setErrors(error.errors)
        toast.error(error.message)
      } else {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      }
    } finally {
      setSaving(false)
    }
  }

  const isDraftContext = quiz === null || quiz.status === "draft"

  // The working paper as a QuizDetail the student-view preview can render —
  // exactly what the current unsaved state would publish.
  const draftPreview = useMemo<QuizDetail>(() => {
    const groups: Record<number, QuestionGroupStem> = { ...(quiz?.groups ?? {}) }
    for (const entry of picked.values()) {
      const parentId = entry.question.parent_id
      if (parentId && !groups[parentId]) {
        const parent = combinedRows.find((row) => row.id === parentId)
        if (parent) {
          groups[parentId] = { id: parentId, stem: parent.body.stem, attachments: parent.body.attachments }
        }
      }
    }

    return {
      ...(quiz ?? ({
        id: 0,
        is_platform: platform,
        subject_assignment_id: null,
        subject_id: null,
        grade_level_id: null,
        status: "draft",
        draw: null,
        has_access_code: false,
        assessment_id: null,
        published_at: null,
        closed_at: null,
        created_at: "",
        can_edit: true,
        can_delete: true,
      } as unknown as Quiz)),
      kind,
      title: title || t("exams.title"),
      instructions: instructions || null,
      language,
      total_points: totalPoints,
      settings: { duration_minutes: duration ? Number(duration) : 0 },
      draw: null,
      parts: parts.map((part) => ({ title: part.title, instructions: part.instructions || null })),
      questions: paper.ordered.map((entry, index) => ({
        ...entry.question,
        quiz_points: Number(entry.points || entry.question.points),
        sort_order: index,
        part_index: entry.part,
      })),
      groups,
    }
  }, [quiz, picked, combinedRows, kind, title, instructions, language, totalPoints, duration, parts, paper, platform, t])

  async function openStudioPreview() {
    if (mode === "fixed") {
      setPreviewOpen(true)
      return
    }
    if (!quiz) return
    setPreviewLoading(true)
    try {
      const res = await apiFetch<{
        data: { questions: QuizDetail["questions"]; groups: QuizDetail["groups"] }
      }>(`/quizzes/${quiz.id}/preview`)
      setPreviewSample({ ...draftPreview, questions: res.data.questions, groups: res.data.groups })
      setPreviewOpen(true)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setPreviewLoading(false)
    }
  }

  // The kind (quiz/exam/mock) + timing shape the whole paper, so on mobile
  // (where the settings rail stacks BELOW the canvas) they surface as a setup
  // card first. One controlled tree, mounted twice: above the canvas (mobile)
  // and at the top of the settings rail (desktop).
  const kindSetup = (
    <>
      <div className="space-y-2">
        <Label>{t("exams.kind")}</Label>
        <Select value={kind} onValueChange={(v) => setKind(v as QuizKind)}>
          <SelectTrigger className="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {(platform ? ["mock", "exam", "quiz"] : ["quiz", "exam"]).map((option) => (
              <SelectItem key={option} value={option}>
                {t(`exams.kinds.${option}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-2">
          <Label>{t("exams.duration")}</Label>
          <Input
            type="number"
            min="0"
            max="600"
            className="no-spinner"
            value={duration}
            onChange={(e) => setDuration(e.target.value)}
          />
        </div>
        <div className="space-y-2">
          <Label>{t("exams.attemptsAllowed")}</Label>
          <Input
            type="number"
            min="0"
            max="10"
            className="no-spinner"
            value={attemptsAllowed}
            onChange={(e) => setAttemptsAllowed(e.target.value)}
          />
        </div>
      </div>
      <p className="-mt-3 text-xs text-muted-foreground">{t("exams.durationHint")}</p>
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
                <FileText className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {quiz ? t("exams.edit") : t("exams.add")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {platform ? t("exams.platformTitle") : title || t("exams.title")}
                </p>
              </div>
            </div>

            <div className="ml-auto flex items-center gap-2">
              {(mode === "fixed" ? picked.size > 0 : quiz !== null) && (
                <Button
                  type="button"
                  variant="outline"
                  className="h-10"
                  disabled={previewLoading}
                  onClick={() => void openStudioPreview()}
                >
                  {previewLoading ? <Loader2 className="size-4 animate-spin" /> : <Eye className="size-4" />}
                  <span className="hidden sm:inline">{t("exams.preview")}</span>
                </Button>
              )}
              {isDraftContext ? (
                // The split button's halves stay FLUSH — the parent's gap-2
                // must never open a seam between them.
                <div className="flex items-center">
                  <Button className="h-10 rounded-r-none px-4 md:px-5" disabled={saving || imgUploading} onClick={() => save("publish")}>
                    {saving && <Loader2 className="size-4 animate-spin" />}
                    {t("exams.saveAndPublish")}
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
                      <DropdownMenuItem onClick={() => save("publish")}>
                        <Check className="size-4" /> {t("exams.saveAndPublish")}
                      </DropdownMenuItem>
                      <DropdownMenuItem onClick={() => save("draft")}>
                        {t("questions.saveDraft")}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              ) : (
                <Button className="h-10 px-5" disabled={saving || imgUploading} onClick={() => save()}>
                  {saving && <Loader2 className="size-4 animate-spin" />}
                  {tc("actions.save")}
                </Button>
              )}
            </div>
          </header>

          {/* ── Body: canvas + settings rail ─────────────────────────── */}
          <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8">
                {/* Mobile setup card — the desktop rail carries the same
                    controls on md+. */}
                <section className="space-y-5 rounded-2xl border bg-card p-4 shadow-xs md:hidden">
                  {kindSetup}
                </section>

                {/* Who takes this */}
                {!platform ? (
                  <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                    <div className="mb-3 flex items-center gap-2">
                      <Users className="size-4 text-muted-foreground" />
                      <Label>{t("exams.audience")}</Label>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("banks.grade")}</Label>
                        <Select
                          value={gradeName || undefined}
                          onValueChange={(v) => {
                            setGradeName(v)
                            setSubjectKey("")
                            setTargetIds([])
                          }}
                        >
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={tc("actions.select")} />
                          </SelectTrigger>
                          <SelectContent>
                            {gradeNames.map((grade) => (
                              <SelectItem key={grade} value={grade}>
                                {grade}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("exams.subject")}</Label>
                        <Select
                          value={subjectKey || undefined}
                          onValueChange={(v) => {
                            setSubjectKey(v)
                            setTargetIds([])
                          }}
                          disabled={gradeName === ""}
                        >
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={tc("actions.select")} />
                          </SelectTrigger>
                          <SelectContent>
                            {subjectChoices.map((subject) => (
                              <SelectItem key={subject.id} value={subject.id}>
                                {subject.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    </div>
                    {subjectKey !== "" && (
                      <div className="mt-4 space-y-2">
                        <Label className="text-muted-foreground">{t("exams.sections")}</Label>
                        <div className="flex flex-wrap gap-2">
                          {sectionChoices.map((option) => {
                            const selected = targetIds.includes(option.subject_assignment_id)
                            return (
                              <button
                                key={option.subject_assignment_id}
                                type="button"
                                onClick={() => {
                                  setTargetIds((prev) =>
                                    selected
                                      ? prev.filter((id) => id !== option.subject_assignment_id)
                                      : [...prev, option.subject_assignment_id],
                                  )
                                  clearError("subject_assignment_ids")
                                }}
                                className={cn(
                                  "inline-flex h-10 items-center gap-1.5 rounded-full border px-4 text-sm font-medium transition-colors",
                                  selected
                                    ? "border-primary/40 bg-primary/10 text-primary"
                                    : "hover:bg-muted/60",
                                )}
                              >
                                {selected && <Check className="size-3.5" />}
                                {option.section.name}
                              </button>
                            )
                          })}
                          {sectionChoices.length === 0 && (
                            <p className="text-sm text-muted-foreground">{t("exams.noSections")}</p>
                          )}
                        </div>
                        <p className="text-xs text-muted-foreground">{t("exams.sectionsHint")}</p>
                      </div>
                    )}
                    {errors.subject_assignment_ids && (
                      <p className="mt-2 text-xs text-destructive">{errors.subject_assignment_ids[0]}</p>
                    )}
                  </section>
                ) : (
                  <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                    <div className="mb-3 flex items-center gap-2">
                      <Users className="size-4 text-muted-foreground" />
                      <Label>{t("exams.audience")}</Label>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("exams.forGrade")}</Label>
                        <Select value={gradeLevelId || "none"} onValueChange={(v) => setGradeLevelId(v === "none" ? "" : v)}>
                          <SelectTrigger className="w-full">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="none">—</SelectItem>
                            {gradeLevels.map((grade) => (
                              <SelectItem key={grade.id} value={String(grade.id)}>
                                {grade.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("exams.subject")}</Label>
                        <Select value={subjectId || "none"} onValueChange={(v) => setSubjectId(v === "none" ? "" : v)}>
                          <SelectTrigger className="w-full">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="none">—</SelectItem>
                            {subjects.map((subject) => (
                              <SelectItem key={subject.id} value={String(subject.id)}>
                                {subject.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("exams.examKind")}</Label>
                        <Select value={examKind || "none"} onValueChange={(v) => setExamKind(v === "none" ? "" : v)}>
                          <SelectTrigger className="w-full">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="none">—</SelectItem>
                            <SelectItem value="national_past">{t("exams.examKinds.national_past")}</SelectItem>
                            <SelectItem value="mock">{t("exams.examKinds.mock")}</SelectItem>
                            <SelectItem value="practice">{t("exams.examKinds.practice")}</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-2">
                          <Label className="text-muted-foreground">{t("exams.examYearEc")}</Label>
                          <Input
                            type="number"
                            min="1980"
                            max="2100"
                            className="no-spinner"
                            value={examYearEc}
                            onChange={(e) => setExamYearEc(e.target.value)}
                            placeholder="2016"
                          />
                        </div>
                        <div className="space-y-2">
                          <Label className="text-muted-foreground">{t("courses.stream")}</Label>
                          <Select value={stream || "all"} onValueChange={(v) => setStream(v === "all" ? "" : v)}>
                            <SelectTrigger className="w-full">
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="all">{t("courses.streamAll")}</SelectItem>
                              <SelectItem value="natural">{t("courses.streamNatural")}</SelectItem>
                              <SelectItem value="social">{t("courses.streamSocial")}</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                      </div>
                    </div>
                  </section>
                )}

                {/* The exam */}
                <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <div className="space-y-2">
                    <Label>
                      {t("exams.examTitle")} <span className="text-destructive">*</span>
                    </Label>
                    <Input
                      value={title}
                      onChange={(e) => {
                        setTitle(e.target.value)
                        clearError("title")
                      }}
                    />
                    {errors.title && <p className="text-destructive text-xs">{errors.title[0]}</p>}
                  </div>
                  <div className="space-y-2">
                    <Label>{t("exams.instructions")}</Label>
                    <RichTextEditor
                      value={instructions}
                      onChange={setInstructions}
                      placeholder={t("exams.instructionsPlaceholder")}
                      onUploadingChange={setImgUploading}
                      onUploadImage={uploadImage}
                    />
                    <p className="text-xs text-muted-foreground">{t("exams.instructionsHint")}</p>
                  </div>
                </section>

                {/* The paper */}
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <Label>{t("exams.questionsTab")}</Label>
                    {!structureLocked && (
                      <div className="flex items-center gap-2">
                        {banksError ? (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-9 gap-1.5"
                                onClick={() => setBanksRetry((n) => n + 1)}
                              >
                                <RefreshCw className="size-3.5" /> {t("exams.banksRetry")}
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>{t("exams.banksLoadFailed")}</TooltipContent>
                          </Tooltip>
                        ) : !banksLoading && writableBanks.length === 0 ? (
                          // Disabled buttons swallow hover — the wrapping span
                          // carries the tooltip explaining WHY it's disabled.
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <span tabIndex={0} className="inline-flex">
                                <Button type="button" variant="outline" size="sm" className="h-9 gap-1.5" disabled>
                                  <Plus className="size-3.5" /> {t("exams.newQuestion")}
                                </Button>
                              </span>
                            </TooltipTrigger>
                            <TooltipContent>{t("exams.noWritableBanks")}</TooltipContent>
                          </Tooltip>
                        ) : (
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-9 gap-1.5"
                                loading={banksLoading}
                              >
                                <Plus className="size-3.5" /> {t("exams.newQuestion")}
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="max-h-72 w-64 overflow-y-auto">
                              {(selectedBankIds.length > 0
                                ? writableBanks.filter((bank) => selectedBankIds.includes(bank.id))
                                : writableBanks
                              ).map((bank) => (
                                <DropdownMenuItem
                                  key={bank.id}
                                  onClick={() => {
                                    setNewQuestionBankId(bank.id)
                                    setNewQuestionOpen(true)
                                  }}
                                >
                                  {bank.name}
                                </DropdownMenuItem>
                              ))}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        )}
                        <div className="flex gap-1 rounded-full border p-0.5">
                          {(["fixed", "draw"] as const).map((option) => (
                            <button
                              key={option}
                              type="button"
                              onClick={() => setMode(option)}
                              className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                mode === option ? "bg-primary text-primary-foreground" : "text-muted-foreground"
                              }`}
                            >
                              {option === "fixed" ? t("exams.fixedPaper") : t("exams.randomDraw")}
                            </button>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>

                  {!structureLocked && draftPickedCount > 0 && (
                    <div className="mb-3 flex items-start gap-2 rounded-xl border border-warning/30 bg-warning/10 px-3.5 py-2.5 text-sm text-warning">
                      <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                      <p>{t("exams.draftPicksWarning", { count: draftPickedCount })}</p>
                    </div>
                  )}

                  {structureLocked ? (
                    <p className="rounded-xl bg-warning/10 px-3.5 py-2.5 text-sm text-warning">
                      {t("exams.structureLocked")}
                    </p>
                  ) : mode === "fixed" ? (
                    <div className="space-y-2.5">
                      {/* banks + running total */}
                      <div className="flex flex-wrap items-center gap-1.5">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button type="button" variant="outline" size="sm" className="h-9 gap-1.5">
                              {selectedBankIds.length === 0
                                ? t("exams.fromBanks")
                                : t("exams.banksSelected", { count: selectedBankIds.length })}
                              <ChevronDown className="size-3.5 opacity-60" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" className="max-h-72 w-72 overflow-y-auto">
                            {banks.length === 0 ? (
                              <p className="px-2 py-3 text-sm text-muted-foreground">{t("questions.empty")}</p>
                            ) : (
                              banks.map((bank) => (
                                <DropdownMenuCheckboxItem
                                  key={bank.id}
                                  checked={selectedBankIds.includes(bank.id)}
                                  onSelect={(e) => e.preventDefault()}
                                  onCheckedChange={(checked) =>
                                    setSelectedBankIds((prev) =>
                                      checked ? [...prev, bank.id] : prev.filter((id) => id !== bank.id),
                                    )
                                  }
                                >
                                  {bank.name}
                                  {bank.questions_count !== undefined ? ` (${bank.questions_count})` : ""}
                                </DropdownMenuCheckboxItem>
                              ))
                            )}
                          </DropdownMenuContent>
                        </DropdownMenu>

                        {selectedBankIds.map((id) => {
                          const bank = banks.find((b) => b.id === id)
                          if (!bank) return null
                          return (
                            <Badge key={id} variant="secondary" className="gap-1 py-1 pr-1 pl-2">
                              <span className="max-w-32 truncate">{bank.name}</span>
                              <button
                                type="button"
                                onClick={() => setSelectedBankIds((prev) => prev.filter((bid) => bid !== id))}
                                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                                aria-label={tc("actions.delete")}
                              >
                                <X className="size-3" />
                              </button>
                            </Badge>
                          )
                        })}

                        <p className="ml-auto flex items-center gap-1.5 text-sm text-muted-foreground">
                          {poolLoading && <Loader2 className="size-3.5 animate-spin" />}
                          <Layers className="size-3.5" />
                          {t("exams.pickedCount", { count: picked.size, points: totalPoints })}
                        </p>
                      </div>

                      {selectedBankIds.length === 0 && picked.size === 0 ? (
                        <p className="rounded-xl border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
                          {t("exams.noBankSelected")}
                        </p>
                      ) : (
                        <>
                          {/* search + part actions on one line */}
                          <div className="flex flex-wrap items-center gap-1.5">
                            <Input
                              value={poolQuery}
                              onChange={(e) => setPoolQuery(e.target.value)}
                              placeholder={t("questions.search")}
                              className="h-9 min-w-40 flex-1"
                            />
                            {/* Always offered: re-running regroups the WHOLE
                                paper by type, folding in questions added after
                                the first grouping. */}
                            {picked.size > 1 && (
                              <Button type="button" variant="outline" size="sm" className="h-9 gap-1.5" onClick={groupByType}>
                                <Sparkles className="size-3.5" /> {t("exams.groupByType")}
                              </Button>
                            )}
                            <Button type="button" variant="outline" size="sm" className="h-9 gap-1.5" onClick={addPart}>
                              <Plus className="size-3.5" /> {t("exams.addPart")}
                            </Button>
                          </div>

                          {/* ONE list: the arranged paper (parts as headers), then the rest of the pool */}
                          <div className="max-h-[36rem] overflow-y-auto rounded-xl border">
                            {/* parts + their questions */}
                            {parts.map((part, pi) => {
                              const bucket = paper.buckets[pi] ?? []
                              const visible = bucket.filter((item) => matchingIds.has(item.id))
                              const bucketPoints = bucket.reduce((sum, item) => sum + Number(item.points || 0), 0)
                              return (
                                <div
                                  key={pi}
                                  className={cn(
                                    "transition-colors",
                                    dragId !== null && dragOverPart === pi && "bg-primary/5 ring-1 ring-inset ring-primary/30",
                                  )}
                                  onDragOver={(e) => {
                                    if (dragId === null) return
                                    e.preventDefault()
                                    e.dataTransfer.dropEffect = "move"
                                    if (dragOverPart !== pi) setDragOverPart(pi)
                                  }}
                                  onDragLeave={() => setDragOverPart((prev) => (prev === pi ? "idle" : prev))}
                                  onDrop={(e) => {
                                    e.preventDefault()
                                    if (dragId !== null) setQuestionPart(dragId, pi)
                                    setDragId(null)
                                    setDragOverId(null)
                                    setDragOverPart("idle")
                                  }}
                                >
                                  <div className="space-y-1.5 border-b bg-muted/40 p-2.5 [&:not(:first-child)]:border-t">
                                    <div className="flex items-center gap-2">
                                      <Badge variant="secondary" className="shrink-0 font-semibold">
                                        {t("exams.partLabel", { numeral: romanNumeral(pi + 1) })}
                                      </Badge>
                                      <Input
                                        value={part.title}
                                        onChange={(e) => {
                                          const value = e.target.value
                                          setParts((prev) => prev.map((p, i) => (i === pi ? { ...p, title: value } : p)))
                                          clearError("parts")
                                        }}
                                        placeholder={t("exams.partTitlePlaceholder")}
                                        className="h-8 flex-1 bg-card"
                                      />
                                      <span className="hidden shrink-0 text-xs tabular-nums text-muted-foreground sm:block">
                                        {t("exams.partSummary", { count: bucket.length, points: bucketPoints })}
                                      </span>
                                      <div className="flex shrink-0 items-center">
                                        <button
                                          type="button"
                                          onClick={() => movePart(pi, -1)}
                                          disabled={pi === 0}
                                          className="rounded p-1 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                          aria-label={t("exams.moveUp")}
                                        >
                                          <ChevronUp className="size-3.5" />
                                        </button>
                                        <button
                                          type="button"
                                          onClick={() => movePart(pi, 1)}
                                          disabled={pi === parts.length - 1}
                                          className="rounded p-1 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                          aria-label={t("exams.moveDown")}
                                        >
                                          <ChevronDown className="size-3.5" />
                                        </button>
                                        <Button
                                          type="button"
                                          variant="ghost"
                                          size="icon"
                                          className="size-7 text-muted-foreground"
                                          onClick={() => removePart(pi)}
                                          aria-label={t("exams.removePart")}
                                        >
                                          <Trash2 className="size-3.5" />
                                        </Button>
                                      </div>
                                    </div>
                                    <Textarea
                                      value={part.instructions}
                                      onChange={(e) => {
                                        const value = e.target.value
                                        setParts((prev) =>
                                          prev.map((p, i) => (i === pi ? { ...p, instructions: value } : p)),
                                        )
                                      }}
                                      placeholder={t("exams.partInstructionsPlaceholder")}
                                      rows={1}
                                      className="min-h-8 bg-card text-sm"
                                    />
                                  </div>
                                  {visible.length === 0 ? (
                                    <p className="border-b px-3 py-2.5 text-center text-xs text-muted-foreground">
                                      {t("exams.partEmpty")}
                                    </p>
                                  ) : (
                                    visible.map((item) => (
                                      <QuestionRow
                                        key={item.id}
                                        question={item.question}
                                        entry={item}
                                        position={bucket.findIndex((row) => row.id === item.id)}
                                        bucketSize={bucket.length}
                                        number={pickedOrder.get(item.id) ?? 0}
                                        parts={parts}
                                        dragId={dragId}
                                        dragOverId={dragOverId}
                                        setDragId={setDragId}
                                        setDragOverId={setDragOverId}
                                        setDragOverPart={setDragOverPart}
                                        reorderPicked={reorderPicked}
                                        movePicked={movePicked}
                                        setQuestionPart={setQuestionPart}
                                        togglePick={togglePick}
                                        setPoints={(id, points) =>
                                          setPicked((prev) => {
                                            const entry = prev.get(id)
                                            if (!entry) return prev
                                            const next = new Map(prev)
                                            next.set(id, { ...entry, points })
                                            return next
                                          })
                                        }
                                      />
                                    ))
                                  )}
                                </div>
                              )
                            })}

                            {/* unassigned picked questions (the whole paper when no parts) */}
                            {(paper.none.length > 0 || parts.length > 0) && (
                              <div
                                className={cn(
                                  "transition-colors",
                                  dragId !== null && dragOverPart === "none" && "bg-primary/5 ring-1 ring-inset ring-primary/30",
                                )}
                                onDragOver={(e) => {
                                  if (dragId === null || parts.length === 0) return
                                  e.preventDefault()
                                  e.dataTransfer.dropEffect = "move"
                                  if (dragOverPart !== "none") setDragOverPart("none")
                                }}
                                onDragLeave={() => setDragOverPart((prev) => (prev === "none" ? "idle" : prev))}
                                onDrop={(e) => {
                                  if (parts.length === 0) return
                                  e.preventDefault()
                                  if (dragId !== null) setQuestionPart(dragId, null)
                                  setDragId(null)
                                  setDragOverId(null)
                                  setDragOverPart("idle")
                                }}
                              >
                                {parts.length > 0 && paper.none.length > 0 && (
                                  <p className="border-b bg-muted/40 px-3 py-1.5 text-xs font-medium text-muted-foreground [&:not(:first-child)]:border-t">
                                    {t("exams.unassigned")}
                                  </p>
                                )}
                                {paper.none
                                  .filter((item) => matchingIds.has(item.id))
                                  .map((item) => (
                                    <QuestionRow
                                      key={item.id}
                                      question={item.question}
                                      entry={item}
                                      position={paper.none.findIndex((row) => row.id === item.id)}
                                      bucketSize={paper.none.length}
                                      number={pickedOrder.get(item.id) ?? 0}
                                      parts={parts}
                                      dragId={dragId}
                                      dragOverId={dragOverId}
                                      setDragId={setDragId}
                                      setDragOverId={setDragOverId}
                                      setDragOverPart={setDragOverPart}
                                      reorderPicked={reorderPicked}
                                      movePicked={movePicked}
                                      setQuestionPart={setQuestionPart}
                                      togglePick={togglePick}
                                      setPoints={(id, points) =>
                                        setPicked((prev) => {
                                          const entry = prev.get(id)
                                          if (!entry) return prev
                                          const next = new Map(prev)
                                          next.set(id, { ...entry, points })
                                          return next
                                        })
                                      }
                                    />
                                  ))}
                              </div>
                            )}

                            {/* the rest of the browsed pool — tick to add */}
                            {unpickedRows.length > 0 && (
                              <>
                                {picked.size > 0 && (
                                  <p className="border-y bg-muted/40 px-3 py-1.5 text-xs font-medium text-muted-foreground">
                                    {t("exams.addFromBanks")}
                                  </p>
                                )}
                                {unpickedRows.map((question) => (
                                  <QuestionRow
                                    key={question.id}
                                    question={question}
                                    entry={undefined}
                                    position={0}
                                    bucketSize={0}
                                    number={0}
                                    parts={parts}
                                    dragId={dragId}
                                    dragOverId={dragOverId}
                                    setDragId={setDragId}
                                    setDragOverId={setDragOverId}
                                    setDragOverPart={setDragOverPart}
                                    reorderPicked={reorderPicked}
                                    movePicked={movePicked}
                                    setQuestionPart={setQuestionPart}
                                    togglePick={togglePick}
                                    setPoints={() => {}}
                                  />
                                ))}
                              </>
                            )}

                            {picked.size === 0 && unpickedRows.length === 0 && parts.length === 0 && (
                              <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                {t("questions.empty")}
                              </p>
                            )}
                          </div>
                        </>
                      )}
                      {errors.parts && <p className="text-destructive text-xs">{errors.parts[0]}</p>}
                      {errors.questions && <p className="text-destructive text-xs">{errors.questions[0]}</p>}
                    </div>
                  ) : (
                    <div className="space-y-3">
                      <p className="text-xs text-muted-foreground">{t("exams.randomDrawHint")}</p>
                      {draw.map((rule, index) => (
                        <div key={index} className="flex flex-wrap items-center gap-2 rounded-xl border p-2.5">
                          <Select
                            value={rule.question_bank_id ? String(rule.question_bank_id) : undefined}
                            onValueChange={(v) =>
                              setDraw((prev) =>
                                prev.map((r, i) => (i === index ? { ...r, question_bank_id: Number(v) } : r)),
                              )
                            }
                          >
                            <SelectTrigger className="w-44 flex-1">
                              <SelectValue placeholder={t("exams.fromBank")} />
                            </SelectTrigger>
                            <SelectContent>
                              {banks.map((bank) => (
                                <SelectItem key={bank.id} value={String(bank.id)}>
                                  {bank.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                          <Input
                            type="number"
                            min="1"
                            value={rule.count || ""}
                            onChange={(e) =>
                              setDraw((prev) =>
                                prev.map((r, i) => (i === index ? { ...r, count: Number(e.target.value) } : r)),
                              )
                            }
                            className="no-spinner h-9 w-20 text-center"
                            placeholder={t("exams.drawCount")}
                          />
                          <Select
                            value={rule.difficulty ?? "any"}
                            onValueChange={(v) =>
                              setDraw((prev) =>
                                prev.map((r, i) =>
                                  i === index ? { ...r, difficulty: v === "any" ? null : (v as "easy") } : r,
                                ),
                              )
                            }
                          >
                            <SelectTrigger className="w-28">
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="any">{t("questions.difficultyAny")}</SelectItem>
                              <SelectItem value="easy">{t("questions.easy")}</SelectItem>
                              <SelectItem value="medium">{t("questions.medium")}</SelectItem>
                              <SelectItem value="hard">{t("questions.hard")}</SelectItem>
                            </SelectContent>
                          </Select>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground"
                            title={tc("actions.delete")}
                            aria-label={tc("actions.delete")}
                            onClick={() => setDraw((prev) => prev.filter((_, i) => i !== index))}
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                      ))}
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setDraw((prev) => [...prev, { question_bank_id: 0, count: 10 }])}
                      >
                        <Plus className="size-3.5" /> {t("exams.addRule")}
                      </Button>
                      {errors.draw && <p className="text-destructive text-xs">{errors.draw[0]}</p>}
                    </div>
                  )}
                </section>
              </div>
            </main>

            {/* Settings rail */}
            <aside className="border-t bg-background md:min-h-0 md:w-[340px] md:shrink-0 md:overflow-y-auto md:border-l md:border-t-0">
              <div className="space-y-5 p-4 md:p-5">
                <div className="hidden space-y-5 md:block">{kindSetup}</div>

                <DateTimeField
                  label={t("exams.opensAt")}
                  value={opensAt}
                  min={addisToday()}
                  onChange={(v) => {
                    setOpensAt(v)
                    clearError("settings.closes_at")
                  }}
                />
                <div className="space-y-1">
                  <DateTimeField
                    label={t("exams.closesAt")}
                    value={closesAt}
                    min={opensAt?.slice(0, 10) || addisToday()}
                    onChange={(v) => {
                      setClosesAt(v)
                      clearError("settings.closes_at")
                    }}
                  />
                  {errors["settings.closes_at"] && (
                    <p className="text-destructive text-xs">{errors["settings.closes_at"][0]}</p>
                  )}
                </div>

                <div className="space-y-2.5">
                  <label className="flex items-center justify-between rounded-xl border px-3.5 py-2.5">
                    <span className="text-sm">{t("exams.shuffleQuestions")}</span>
                    <Switch checked={shuffleQuestions} onCheckedChange={setShuffleQuestions} />
                  </label>
                  <label className="flex items-center justify-between rounded-xl border px-3.5 py-2.5">
                    <span className="text-sm">{t("exams.shuffleOptions")}</span>
                    <Switch checked={shuffleOptions} onCheckedChange={setShuffleOptions} />
                  </label>
                  <label className="flex items-center justify-between rounded-xl border px-3.5 py-2.5">
                    <span className="text-sm">{t("exams.revealAnswers")}</span>
                    <Switch checked={revealAnswers} onCheckedChange={setRevealAnswers} />
                  </label>
                </div>

                <div className="space-y-2">
                  <Label>{t("exams.navigation")}</Label>
                  <Select value={navigation} onValueChange={(v) => setNavigation(v as "free")}>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="free">{t("exams.navFree")}</SelectItem>
                      <SelectItem value="sequential">{t("exams.navSequential")}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label>{t("exams.resultsPolicy")}</Label>
                  <Select value={resultsPolicy} onValueChange={setResultsPolicy}>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="immediately">{t("exams.resultsImmediately")}</SelectItem>
                      <SelectItem value="after_close">{t("exams.resultsAfterClose")}</SelectItem>
                      <SelectItem value="manual">{t("exams.resultsManual")}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {Number(attemptsAllowed) !== 1 && (
                  <div className="space-y-2">
                    <Label>{t("exams.gradeAttempt")}</Label>
                    <Select value={gradeAttempt} onValueChange={setGradeAttempt}>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="best">{t("exams.gradeBest")}</SelectItem>
                        <SelectItem value="last">{t("exams.gradeLast")}</SelectItem>
                        <SelectItem value="first">{t("exams.gradeFirst")}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                )}

                <div className="space-y-2">
                  <Label>{t("exams.accessCode")}</Label>
                  <Input
                    value={accessCode}
                    onChange={(e) => setAccessCode(e.target.value)}
                    placeholder={quiz?.has_access_code ? t("exams.accessCodeKeep") : undefined}
                  />
                  <p className="text-xs text-muted-foreground">{t("exams.accessCodeHint")}</p>
                </div>

                {!platform && (
                  <div className="space-y-2">
                    <Label>{t("exams.gradebookSlot")}</Label>
                    <Select
                      value={assessmentId || "none"}
                      onValueChange={(v) => setAssessmentId(v === "none" ? "" : v)}
                    >
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">{t("exams.gradebookNone")}</SelectItem>
                        {slots.map((slot) => (
                          <SelectItem key={slot.id} value={String(slot.id)}>
                            {slot.name}
                            {slot.max_score !== undefined ? ` (/${Number(slot.max_score)})` : ""}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                      {targetIds.length > 1 ? t("exams.gradebookMultiHint") : t("exams.gradebookHint")}
                    </p>
                    {errors.assessment_id && (
                      <p className="text-destructive text-xs">{errors.assessment_id[0]}</p>
                    )}
                  </div>
                )}

                {targetIds.length > 0 && (
                  <div className="rounded-xl bg-muted/50 px-3.5 py-3 text-xs text-muted-foreground">
                    <Badge variant="secondary" className="mb-1.5">
                      {t("exams.sectionsSelected", { count: targetIds.length })}
                    </Badge>
                    <p>{t("exams.audienceSummary")}</p>
                  </div>
                )}
              </div>
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
    <QuestionEditor
      bank={banks.find((bank) => bank.id === newQuestionBankId) ?? null}
      question={null}
      open={newQuestionOpen}
      onOpenChange={setNewQuestionOpen}
      onSaved={handleQuestionCreated}
    />
    <ExamPreview
      quiz={mode === "draw" ? (previewSample ?? draftPreview) : draftPreview}
      sample={mode === "draw"}
      open={previewOpen}
      onOpenChange={(o) => {
        setPreviewOpen(o)
        if (!o) setPreviewSample(null)
      }}
    />
    </>
  )
}

/**
 * One row of the single exam-paper list. Tick the checkbox to put a bank
 * question on the paper (untick to take it off); picked rows grow their
 * controls in place — points, part menu, drag handle, chevrons, delete —
 * exactly one list, never a second copy.
 */
function QuestionRow({
  question,
  entry,
  position,
  bucketSize,
  number,
  parts,
  dragId,
  dragOverId,
  setDragId,
  setDragOverId,
  setDragOverPart,
  reorderPicked,
  movePicked,
  setQuestionPart,
  togglePick,
  setPoints,
}: {
  question: Question
  entry: { id: number; question: Question; points: string; part: number | null } | undefined
  position: number
  bucketSize: number
  number: number
  parts: { title: string; instructions: string }[]
  dragId: number | null
  dragOverId: number | null
  setDragId: (id: number | null) => void
  setDragOverId: (id: number | null) => void
  setDragOverPart: (part: number | null | "none" | "idle") => void
  reorderPicked: (sourceId: number, targetId: number) => void
  movePicked: (id: number, direction: -1 | 1) => void
  setQuestionPart: (id: number, part: number | null) => void
  togglePick: (question: Question, checked: boolean) => void
  setPoints: (id: number, points: string) => void
}) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const isChecked = entry !== undefined

  return (
    <div
      className={cn(
        "flex items-center gap-2.5 border-b px-2.5 py-2 transition-colors last:border-b-0",
        !isChecked && "hover:bg-muted/40",
        dragId !== null && isChecked && dragOverId === question.id && dragId !== question.id &&
          "bg-primary/5 ring-1 ring-inset ring-primary/30",
        dragId === question.id && "opacity-50",
      )}
      onDragOver={(e) => {
        if (dragId === null || !isChecked) return
        e.preventDefault()
        e.stopPropagation()
        e.dataTransfer.dropEffect = "move"
        if (dragOverId !== question.id) setDragOverId(question.id)
      }}
      onDragLeave={() => setDragOverId(null)}
      onDrop={(e) => {
        if (!isChecked) return
        e.preventDefault()
        e.stopPropagation()
        if (dragId !== null) reorderPicked(dragId, question.id)
        setDragId(null)
        setDragOverId(null)
        setDragOverPart("idle")
      }}
    >
      {isChecked ? (
        <span
          draggable
          onDragStart={(e) => {
            setDragId(question.id)
            e.dataTransfer.effectAllowed = "move"
          }}
          onDragEnd={() => {
            setDragId(null)
            setDragOverId(null)
            setDragOverPart("idle")
          }}
          className="hidden shrink-0 cursor-grab touch-none text-muted-foreground/60 hover:text-muted-foreground active:cursor-grabbing sm:block"
          aria-label={t("exams.dragToReorder")}
        >
          <GripVertical className="size-4" />
        </span>
      ) : (
        <span className="hidden w-4 shrink-0 sm:block" />
      )}

      <Checkbox
        checked={isChecked}
        onCheckedChange={(checked) => togglePick(question, checked === true)}
        aria-label={stemText(question.body.stem)}
      />

      {isChecked && (
        <span className="w-6 shrink-0 text-right text-sm font-medium tabular-nums text-muted-foreground">
          {number}.
        </span>
      )}

      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-1 truncate text-sm">
          {question.parent_id ? (
            <CornerDownRight className="size-3 shrink-0 text-muted-foreground" />
          ) : question.type === "group" ? (
            <Layers className="size-3 shrink-0 text-primary" />
          ) : null}
          <span className="truncate">{stemText(question.body.stem)}</span>
          {question.status === "draft" && (
            <Badge
              variant="outline"
              className="shrink-0 border-transparent bg-warning/10 px-1.5 py-0 text-[10px] font-medium text-warning"
            >
              {t("questions.draft")}
            </Badge>
          )}
        </p>
        <p className="truncate text-[11px] text-muted-foreground">
          {t(`questions.types.${question.type}`)}
          {question.type === "group"
            ? ` · ${t("questions.subCount", { count: question.children_count ?? 0 })}`
            : !isChecked
              ? ` · ${Number(question.points)} ${t("questions.points").toLowerCase()}`
              : ""}
          {question.bank_name ? ` · ${question.bank_name}` : ""}
        </p>
      </div>

      {isChecked && (
        <>
          <Input
            type="number"
            min="0.25"
            step="0.25"
            value={entry.points}
            onChange={(e) => setPoints(question.id, e.target.value)}
            className="no-spinner h-8 w-14 shrink-0 text-center text-sm"
            aria-label={t("questions.points")}
          />

          {parts.length > 0 && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="size-8 shrink-0 text-muted-foreground"
                  aria-label={t("exams.moveToPart")}
                >
                  <FolderInput className="size-3.5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-60">
                {parts.map((part, pi) => (
                  <DropdownMenuItem key={pi} disabled={entry.part === pi} onClick={() => setQuestionPart(question.id, pi)}>
                    <span className="truncate">
                      {t("exams.partLabel", { numeral: romanNumeral(pi + 1) })}
                      {part.title ? ` — ${part.title}` : ""}
                    </span>
                  </DropdownMenuItem>
                ))}
                <DropdownMenuItem disabled={entry.part === null} onClick={() => setQuestionPart(question.id, null)}>
                  {t("exams.noPart")}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          )}

          <div className="flex shrink-0 flex-col">
            <button
              type="button"
              onClick={() => movePicked(question.id, -1)}
              disabled={position <= 0}
              className="rounded p-0.5 text-muted-foreground hover:bg-muted disabled:opacity-30"
              aria-label={t("exams.moveUp")}
            >
              <ChevronUp className="size-3.5" />
            </button>
            <button
              type="button"
              onClick={() => movePicked(question.id, 1)}
              disabled={position >= bucketSize - 1}
              className="rounded p-0.5 text-muted-foreground hover:bg-muted disabled:opacity-30"
              aria-label={t("exams.moveDown")}
            >
              <ChevronDown className="size-3.5" />
            </button>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-8 shrink-0 text-muted-foreground"
            onClick={() => togglePick(question, false)}
            aria-label={tc("actions.delete")}
          >
            <Trash2 className="size-3.5" />
          </Button>
        </>
      )}
    </div>
  )
}
