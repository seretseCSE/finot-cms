"use client"

import {
  BookOpen,
  Check,
  ChevronDown,
  ChevronUp,
  CirclePlay,
  CloudUpload,
  FileQuestion,
  GraduationCap,
  ImageIcon,
  Loader2,
  Paperclip,
  Pencil,
  Plus,
  Trash2,
  Users,
  X,
} from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import {
  AudienceRows,
  audienceRowsFromTargets,
  audienceTargetIds,
  makeAudienceRow,
  type AudienceRow,
} from "@/components/lms/audience-rows"
import { LessonEditor } from "@/components/lms/lesson-editor"
import { useClassOptions } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import {
  DropdownMenu,
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
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Course, CourseLessonRow, CourseLessonType, CourseModuleRow, GradeLevel, Subject } from "@/lib/types"
import { cn } from "@/lib/utils"

const LESSON_ICONS: Record<CourseLessonType, typeof CirclePlay> = {
  video: CirclePlay,
  reading: BookOpen,
  file: Paperclip,
  quiz: FileQuestion,
}

type SaveState = "idle" | "dirty" | "saving" | "saved" | "error"

interface Props {
  /** null = create a new course; the editor autosaves it into existence. */
  courseId: number | null
  platform?: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Fired whenever the course changed on the server (list refresh). */
  onSaved: () => void
}

/**
 * The full-screen course studio (ADR-016 layout convention, one screen for
 * course + modules + lessons). Center canvas: who takes it (subject →
 * grade/section audience rows, the materials model), the course itself
 * (title + WYSIWYG description), then the module/lesson tree — lessons open
 * their own stacked full-screen editor. Right rail: status, sequential,
 * cover. The shell AUTOSAVES: naming the course creates the draft, every
 * change after that persists on a debounce; structure edits (modules,
 * lessons, reorder) hit their endpoints immediately.
 */
export function CourseEditor({ courseId, platform = false, open, onOpenChange, onSaved }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const { classes } = useClassOptions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const coverInput = useRef<HTMLInputElement>(null)

  // ── the course shell ──
  const [id, setId] = useState<number | null>(null)
  const [loading, setLoading] = useState(false)
  const [title, setTitle] = useState("")
  const [description, setDescription] = useState("")
  const [sequential, setSequential] = useState(false)
  const [status, setStatus] = useState<Course["status"]>("draft")
  const [cover, setCover] = useState<File | null>(null)
  const [coverUrl, setCoverUrl] = useState<string | null>(null)

  // school lane: subject → audience rows; platform lane: catalog targeting
  const [subjectKey, setSubjectKey] = useState("")
  const [rows, setRows] = useState<AudienceRow[]>([makeAudienceRow()])
  const [subjectId, setSubjectId] = useState("")
  const [minGrade, setMinGrade] = useState("")
  const [maxGrade, setMaxGrade] = useState("")
  const [stream, setStream] = useState("")

  // ── the structure ──
  const [modules, setModules] = useState<CourseModuleRow[]>([])
  const [newModuleTitle, setNewModuleTitle] = useState("")
  const [addingModule, setAddingModule] = useState(false)
  const [renamingModule, setRenamingModule] = useState<{ id: number; title: string } | null>(null)
  const [lessonOpen, setLessonOpen] = useState(false)
  const [lessonModuleId, setLessonModuleId] = useState<number | null>(null)
  const [editingLesson, setEditingLesson] = useState<CourseLessonRow | null>(null)

  const [subjects, setSubjects] = useState<Subject[]>([])
  const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([])
  const [imgUploading, setImgUploading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  // ── autosave machinery ──
  const [saveState, setSaveState] = useState<SaveState>("idle")
  const [revision, setRevision] = useState(0)
  const [closing, setClosing] = useState(false)
  /** What the server last accepted — the local status select may run ahead. */
  const [persistedStatus, setPersistedStatus] = useState<Course["status"]>("draft")
  const idRef = useRef<number | null>(null)
  /** Bumped on every open. A save flushed while CLOSING (close() → persist)
   *  can land after the editor reopened on a DIFFERENT course — it must never
   *  touch the new session's state, above all never point idRef back at the
   *  old course (that would silently write the new course's edits over it). */
  const sessionRef = useRef(0)
  const serverStatus = useRef<Course["status"]>("draft")
  const savingRef = useRef(false)
  const queuedRef = useRef(false)
  const hydratedRows = useRef(false)
  /** Targets as fetched, stamped with the session that loaded them — null
   *  until the course detail arrives ([] immediately on create). The stamp is
   *  load-bearing: on reopen, effects run BEFORE the reset state lands, so the
   *  PREVIOUS course's targets are still in this state for one render — an
   *  unstamped value would hydrate the old course's subject/sections into the
   *  new one and then block the real hydration behind hydratedRows. */
  const [savedTargets, setSavedTargets] = useState<{ session: number; ids: number[] } | null>(null)
  const changedRef = useRef(false)
  /** Edits not yet handed to a save. saveState can't carry this: a touch()
   *  during an in-flight save must survive that save's "saved" transition. */
  const pendingRef = useRef(false)
  const openRef = useRef(open)
  useEffect(() => {
    openRef.current = open
  }, [open])

  const touch = useCallback(() => {
    pendingRef.current = true
    setSaveState((prev) => (prev === "saving" ? prev : "dirty"))
    setRevision((r) => r + 1)
  }, [])

  // Picked or dropped, a cover image stages the same way.
  const coverDrop = useFileDrop({
    accept: "image/jpeg,image/png,image/webp",
    onFiles: ([picked]) => {
      setCover(picked)
      touch()
    },
  })

  // ── school-lane cascades: subject → its classes → grade rows ──
  const subjectChoices = useMemo(() => {
    const seen = new Map<string, string>()
    for (const option of classes) {
      if (option.subject.id) seen.set(String(option.subject.id), option.subject.name ?? "")
    }
    return [...seen.entries()].map(([key, name]) => ({ id: key, name }))
  }, [classes])

  const classesForSubject = useMemo(
    () => classes.filter((option) => String(option.subject.id) === subjectKey),
    [classes, subjectKey],
  )

  const targetIds = useMemo(
    () => (platform ? [] : audienceTargetIds(rows, classesForSubject)),
    [platform, rows, classesForSubject],
  )

  // Streams exist on preparatory grades (11–12) only — offer the choice
  // when the selected grade window can actually reach one.
  const streamedSorts = useMemo(
    () => gradeLevels.filter((g) => g.cycle === "preparatory").map((g) => g.sort_order),
    [gradeLevels],
  )
  const streamApplies =
    platform &&
    streamedSorts.length > 0 &&
    (minGrade === "" || Number(minGrade) <= Math.max(...streamedSorts)) &&
    (maxGrade === "" || Number(maxGrade) >= Math.min(...streamedSorts))

  useEffect(() => {
    if (streamApplies || stream === "") return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear a choice the window no longer supports
    setStream("")
    touch()
  }, [streamApplies, stream, touch])

  // ── open/reset/load ──
  useEffect(() => {
    if (!open) return
    sessionRef.current += 1
    const session = sessionRef.current
    /* eslint-disable react-hooks/set-state-in-effect -- sync editor with the edited row */
    setErrors({})
    setSaveState("idle")
    // Reset the autosave debounce — a leftover revision from the previous
    // session must never fire a phantom save over freshly loaded data.
    setRevision(0)
    setClosing(false)
    setId(courseId)
    idRef.current = courseId
    serverStatus.current = "draft"
    setPersistedStatus("draft")
    savingRef.current = false
    queuedRef.current = false
    pendingRef.current = false
    hydratedRows.current = false
    setSavedTargets(courseId === null ? { session, ids: [] } : null)
    changedRef.current = false
    setTitle("")
    setDescription("")
    setSequential(false)
    setStatus("draft")
    setCover(null)
    setCoverUrl(null)
    setSubjectKey("")
    setRows([makeAudienceRow()])
    setSubjectId("")
    setMinGrade("")
    setMaxGrade("")
    setStream("")
    setModules([])
    setNewModuleTitle("")
    setRenamingModule(null)
    setLessonOpen(false)
    // A close mid-image-upload must not leave this stuck true — it gates the
    // autosave debounce, which would silently disable saving next session.
    setImgUploading(false)
    setLoading(courseId !== null)
    /* eslint-enable react-hooks/set-state-in-effect */

    let cancelled = false
    if (courseId !== null) {
      apiFetch<{ data: Course }>(`/courses/${courseId}`)
        .then((res) => {
          if (cancelled) return
          const course = res.data
          serverStatus.current = course.status
          setPersistedStatus(course.status)
          setSavedTargets({ session, ids: (course.targets ?? []).map((target) => target.subject_assignment_id) })
          setTitle(course.title)
          setDescription(course.description ?? "")
          setSequential(course.is_sequential)
          setStatus(course.status)
          setCoverUrl(course.cover_url)
          setSubjectId(course.subject_id ? String(course.subject_id) : "")
          setMinGrade(course.min_grade_sort ? String(course.min_grade_sort) : "")
          setMaxGrade(course.max_grade_sort ? String(course.max_grade_sort) : "")
          setStream(course.stream ?? "")
          setModules(course.modules ?? [])
        })
        .catch((error) => {
          toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
          onOpenChange(false)
        })
        .finally(() => !cancelled && setLoading(false))
    }

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
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when the editor (re)opens
  }, [open, courseId, platform])

  // Rebuild subject + audience rows once BOTH the fetched targets and the
  // caller's classes are in. `savedTargets` is state (null = still loading),
  // so this can never mark itself done off a stale pre-fetch render.
  useEffect(() => {
    if (
      !open || platform || hydratedRows.current || savedTargets === null ||
      savedTargets.session !== sessionRef.current || classes.length === 0
    ) return
    hydratedRows.current = true
    const anchor = classes.find((option) => savedTargets.ids.includes(option.subject_assignment_id))
    /* eslint-disable react-hooks/set-state-in-effect -- one-shot resolve from loaded data */
    if (anchor) {
      const key = String(anchor.subject.id ?? "")
      const subjectClasses = classes.filter((option) => String(option.subject.id) === key)
      setSubjectKey(key)
      setRows(audienceRowsFromTargets(savedTargets.ids, subjectClasses) ?? [makeAudienceRow()])
    } else if (subjectChoices.length === 1) {
      // Specialist shortcut: a teacher with exactly one subject skips the
      // pick — the default "all grades" row then targets all their classes.
      setSubjectKey(subjectChoices[0].id)
    }
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [open, platform, savedTargets, classes, subjectChoices])

  // ── autosave: debounce every shell change; create on first save ──
  const canPersist = title.trim() !== "" && (platform || targetIds.length > 0)

  const persist = useCallback(
    async (override?: { status?: Course["status"] }): Promise<Course | null> => {
      if (savingRef.current) {
        queuedRef.current = true
        return null
      }
      savingRef.current = true
      // This closure holds every edit made so far — nothing is pending now.
      pendingRef.current = false
      const session = sessionRef.current
      setSaveState("saving")
      setErrors({})
      try {
        const form = new FormData()
        form.append("title", title)
        form.append("description", description)
        form.append("is_sequential", sequential ? "1" : "0")
        const nextStatus = override?.status ?? status
        if (idRef.current !== null) form.append("status", nextStatus)
        if (platform) {
          if (idRef.current === null) form.append("platform", "1")
          if (subjectId) form.append("subject_id", subjectId)
          if (minGrade) form.append("min_grade_sort", minGrade)
          if (maxGrade) form.append("max_grade_sort", maxGrade)
          form.append("stream", streamApplies ? stream : "")
        } else {
          if (subjectKey) form.append("subject_id", subjectKey)
          targetIds.forEach((tid, index) => form.append(`subject_assignment_ids[${index}]`, String(tid)))
        }
        if (cover) form.append("cover", cover)
        if (idRef.current !== null) form.append("_method", "PUT")

        const res = await apiFetch<{ data: Course }>(
          idRef.current !== null ? `/courses/${idRef.current}` : "/courses",
          { method: "POST", body: form },
        )
        // A stale save (the editor reopened on another course meanwhile)
        // completed on the server but owns none of the current state.
        if (sessionRef.current === session) {
          idRef.current = res.data.id
          serverStatus.current = res.data.status
          setPersistedStatus(res.data.status)
          setId(res.data.id)
          // Only sync the select on an explicit publish — a queued edit made
          // while this save was in flight must not be clobbered.
          if (override?.status !== undefined) setStatus(res.data.status)
          setCoverUrl(res.data.cover_url)
          setCover(null)
          setSaveState("saved")
          changedRef.current = true
        }
        return res.data
      } catch (error) {
        const stale = sessionRef.current !== session
        if (error instanceof ApiError && error.errors) {
          if (!stale) {
            setErrors(error.errors)
            // A refused publish falls back to what the server last accepted.
            if (error.errors.status) setStatus(serverStatus.current)
          }
          toast.error(error.message)
        } else {
          toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        }
        if (!stale) setSaveState("error")
        return null
      } finally {
        savingRef.current = false
        if (queuedRef.current) {
          queuedRef.current = false
          if (openRef.current) {
            setRevision((r) => r + 1)
          } else if (sessionRef.current === session) {
            // Editor closed while this save was in flight with a queued edit —
            // the debounce is gated on `open`, so flush the queue directly.
            void persistRef.current()
          }
        }
      }
    },
    [title, description, sequential, status, platform, subjectId, minGrade, maxGrade, stream,
      streamApplies, subjectKey, targetIds, cover, tc],
  )
  /** Latest persist closure — for post-close flushes from async `finally`s. */
  const persistRef = useRef(persist)
  useEffect(() => {
    persistRef.current = persist
  }, [persist])

  useEffect(() => {
    if (!open || revision === 0 || !canPersist || imgUploading) return
    const timer = setTimeout(() => {
      void persist()
    }, 1200)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce keys on the revision counter
  }, [revision, open, canPersist, imgUploading])

  const close = useCallback(() => {
    // Leaving with a pending edit? Flush it — autosave means never losing
    // work. If a save is in flight this queues; its `finally` flushes then.
    if (pendingRef.current && canPersist) void persist()
    onOpenChange(false)
    if (changedRef.current || canPersist) onSaved()
  }, [canPersist, persist, onOpenChange, onSaved])

  /** Header actions: flush the shell, optionally publish, then leave. */
  async function saveAndClose(publish: boolean) {
    if (!canPersist) {
      setErrors({
        ...(title.trim() === "" ? { title: [tc("validation.required")] } : {}),
        ...(!platform && targetIds.length === 0
          ? { subject_assignment_ids: [t("materials.pickAudienceError")] }
          : {}),
      })
      toast.error(t("questions.errors.fix"))
      return
    }
    let saved = await persist(publish ? { status: "published" } : undefined)
    if (saved === null) return
    if (publish && saved.status !== "published") {
      // First save CREATED the course (status rides updates only) — publish now.
      saved = await persist({ status: "published" })
      if (saved === null) return
    }
    toast.success(publish ? t("courses.published") : t("courses.saved"))
    close()
  }

  // ── structure operations (immediate saves) ──
  const reorder = useCallback((next: CourseModuleRow[]) => {
    setModules(next)
    changedRef.current = true
    if (idRef.current === null) return
    void apiFetch(`/courses/${idRef.current}/reorder`, {
      method: "POST",
      body: { modules: next.map((m) => ({ id: m.id, lesson_ids: m.lessons.map((l) => l.id) })) },
    }).catch(() => undefined)
  }, [])

  async function addModule() {
    if (idRef.current === null || newModuleTitle.trim() === "") return
    setAddingModule(true)
    try {
      const res = await apiFetch<{ data: CourseModuleRow }>(`/courses/${idRef.current}/modules`, {
        method: "POST",
        body: { title: newModuleTitle.trim() },
      })
      setModules((prev) => [...prev, { ...res.data, lessons: res.data.lessons ?? [] }])
      setNewModuleTitle("")
      changedRef.current = true
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setAddingModule(false)
    }
  }

  async function commitModuleRename() {
    const pending = renamingModule
    setRenamingModule(null)
    if (!pending || pending.title.trim() === "") return
    const current = modules.find((m) => m.id === pending.id)
    if (!current || current.title === pending.title.trim()) return
    setModules((prev) => prev.map((m) => (m.id === pending.id ? { ...m, title: pending.title.trim() } : m)))
    changedRef.current = true
    try {
      await apiFetch(`/course-modules/${pending.id}`, { method: "PUT", body: { title: pending.title.trim() } })
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function removeModule(module: CourseModuleRow) {
    try {
      await apiFetch(`/course-modules/${module.id}`, { method: "DELETE" })
      setModules((prev) => prev.filter((m) => m.id !== module.id))
      changedRef.current = true
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function removeLesson(lesson: CourseLessonRow) {
    try {
      await apiFetch(`/course-lessons/${lesson.id}`, { method: "DELETE" })
      setModules((prev) =>
        prev.map((m) => ({ ...m, lessons: m.lessons.filter((l) => l.id !== lesson.id) })),
      )
      changedRef.current = true
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const handleLessonSaved = useCallback((lesson: CourseLessonRow) => {
    setModules((prev) =>
      prev.map((module) => {
        const moduleId = lesson.course_module_id ?? lessonModuleId
        const has = module.lessons.some((l) => l.id === lesson.id)
        if (has) {
          return { ...module, lessons: module.lessons.map((l) => (l.id === lesson.id ? lesson : l)) }
        }
        if (module.id === moduleId) {
          return { ...module, lessons: [...module.lessons, lesson] }
        }
        return module
      }),
    )
    changedRef.current = true
  }, [lessonModuleId])

  function moveModule(index: number, direction: -1 | 1) {
    const swap = index + direction
    if (swap < 0 || swap >= modules.length) return
    const next = [...modules]
    ;[next[index], next[swap]] = [next[swap], next[index]]
    reorder(next)
  }

  function moveLesson(moduleIndex: number, lessonIndex: number, direction: -1 | 1) {
    const lessons = [...modules[moduleIndex].lessons]
    const swap = lessonIndex + direction
    if (swap < 0 || swap >= lessons.length) return
    ;[lessons[lessonIndex], lessons[swap]] = [lessons[swap], lessons[lessonIndex]]
    reorder(modules.map((m, i) => (i === moduleIndex ? { ...m, lessons } : m)))
  }

  async function uploadImage(file: File) {
    const form = new FormData()
    form.append("file", file)
    const res = await apiFetch<{ data: { url: string; path: string } }>("/lms/uploads", {
      method: "POST",
      body: form,
    })
    return res.data
  }

  function clearError(key: string) {
    setErrors((prev) => {
      if (!(key in prev)) return prev
      const next = { ...prev }
      delete next[key]
      return next
    })
  }

  const lessonsTotal = modules.reduce((sum, m) => sum + m.lessons.length, 0)
  const isDraftContext = id === null || persistedStatus === "draft"

  // The audience decides who takes the course — on mobile (where the settings
  // rail stacks BELOW the canvas) it surfaces as the first card. One
  // controlled tree, mounted twice: above the canvas (mobile) and at the top
  // of the settings rail (desktop).
  const audienceSection = (
    <section className="rounded-2xl border bg-card p-3.5 shadow-xs">
      <div className="mb-1 flex items-center gap-2 px-0.5">
        <Users className="size-4 text-muted-foreground" />
        <Label>{t("exams.audience")}</Label>
      </div>
      <p className="mb-3 px-0.5 text-xs text-muted-foreground">{t("courses.audienceHint")}</p>

      {!platform ? (
        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label className="text-xs text-muted-foreground">{t("banks.subject")}</Label>
            <Select
              value={subjectKey || undefined}
              onValueChange={(v) => {
                setSubjectKey(v)
                setRows([makeAudienceRow()])
                clearError("subject_assignment_ids")
                touch()
              }}
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

          {subjectKey === "" ? (
            <p className="rounded-xl border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
              {t("courses.pickSubjectFirst")}
            </p>
          ) : (
            <div>
              <AudienceRows
                classes={classesForSubject}
                rows={rows}
                onChange={(next) => {
                  setRows(next)
                  touch()
                }}
                onInteract={() => clearError("subject_assignment_ids")}
              />
              <div className="mt-3">
                <Badge variant="secondary">
                  {t("materials.audienceSummary", { count: targetIds.length })}
                </Badge>
              </div>
            </div>
          )}
          {errors.subject_assignment_ids && (
            <p className="mt-2 text-xs text-destructive">{errors.subject_assignment_ids[0]}</p>
          )}
        </div>
      ) : (
        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label className="text-xs text-muted-foreground">{t("banks.subject")}</Label>
            <Select
              value={subjectId || "none"}
              onValueChange={(v) => {
                setSubjectId(v === "none" ? "" : v)
                touch()
              }}
            >
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
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label className="text-xs text-muted-foreground">{t("courses.fromGrade")}</Label>
              <Select
                value={minGrade || "none"}
                onValueChange={(v) => {
                  setMinGrade(v === "none" ? "" : v)
                  touch()
                }}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">—</SelectItem>
                  {gradeLevels.map((grade) => (
                    <SelectItem key={grade.id} value={String(grade.sort_order)}>
                      {grade.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs text-muted-foreground">{t("courses.toGrade")}</Label>
              <Select
                value={maxGrade || "none"}
                onValueChange={(v) => {
                  setMaxGrade(v === "none" ? "" : v)
                  touch()
                }}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">—</SelectItem>
                  {gradeLevels.map((grade) => (
                    <SelectItem key={grade.id} value={String(grade.sort_order)}>
                      {grade.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          {streamApplies && (
            <div className="space-y-1.5">
              <Label className="text-xs text-muted-foreground">{t("courses.stream")}</Label>
              <Select
                value={stream || "all"}
                onValueChange={(v) => {
                  setStream(v === "all" ? "" : v)
                  touch()
                }}
              >
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
          )}
        </div>
      )}
    </section>
  )

  const saveHint =
    saveState === "saving" ? (
      <span className="flex items-center gap-1.5"><Loader2 className="size-3 animate-spin" /> {t("courses.autosaveSaving")}</span>
    ) : saveState === "saved" ? (
      <span className="flex items-center gap-1.5"><CloudUpload className="size-3" /> {t("courses.autosaveSaved")}</span>
    ) : saveState === "dirty" ? (
      t("courses.autosaveDirty")
    ) : saveState === "error" ? (
      <span className="text-destructive">{t("courses.autosaveError")}</span>
    ) : null

  return (
    <>
      <DialogPrimitive.Root open={open} onOpenChange={(next) => (next ? onOpenChange(true) : close())}>
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
                onClick={close}
                aria-label={tc("actions.close")}
              >
                <X className="size-5" />
              </Button>
              <div className="flex min-w-0 items-center gap-2.5">
                <div className="hidden size-8 items-center justify-center rounded-lg bg-primary/10 text-primary sm:flex">
                  <GraduationCap className="size-4.5" />
                </div>
                <div className="min-w-0">
                  <DialogPrimitive.Title className="truncate text-sm font-semibold">
                    {courseId !== null || id !== null ? t("courses.edit") : t("courses.add")}
                  </DialogPrimitive.Title>
                  <p className="hidden truncate text-xs text-muted-foreground sm:block">
                    {platform ? t("courses.platformTitle") : title || t("courses.courseTitle")}
                  </p>
                </div>
              </div>

              <div className="ml-auto flex items-center gap-3">
                {saveHint && <span className="hidden text-xs text-muted-foreground sm:block">{saveHint}</span>}
                {isDraftContext ? (
                  <div className="flex items-center">
                    <Button
                      className="h-10 rounded-r-none px-4 md:px-5"
                      disabled={closing || saveState === "saving"}
                      onClick={() => {
                        setClosing(true)
                        void saveAndClose(true).finally(() => setClosing(false))
                      }}
                    >
                      {closing && <Loader2 className="size-4 animate-spin" />}
                      {t("exams.saveAndPublish")}
                    </Button>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button
                          className="h-10 rounded-l-none border-l border-primary-foreground/25 px-2"
                          loading={closing || saveState === "saving"}
                          aria-label={t("questions.saveOptions")}
                        >
                          <ChevronDown className="size-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-52">
                        <DropdownMenuItem
                          onClick={() => {
                            setClosing(true)
                            void saveAndClose(true).finally(() => setClosing(false))
                          }}
                        >
                          <Check className="size-4" /> {t("exams.saveAndPublish")}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                          onClick={() => {
                            setClosing(true)
                            void saveAndClose(false).finally(() => setClosing(false))
                          }}
                        >
                          {t("questions.saveDraft")}
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                ) : (
                  <Button
                    className="h-10 px-5"
                    disabled={closing || saveState === "saving"}
                    onClick={() => {
                      setClosing(true)
                      void saveAndClose(false).finally(() => setClosing(false))
                    }}
                  >
                    {closing && <Loader2 className="size-4 animate-spin" />}
                    {tc("actions.save")}
                  </Button>
                )}
              </div>
            </header>

            {/* ── Body: canvas + settings rail ─────────────────────────── */}
            <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex md:overflow-hidden">
              <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
                <div className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8">
                  {loading ? (
                    <>
                      <Skeleton className="h-40 w-full rounded-2xl" />
                      <Skeleton className="h-56 w-full rounded-2xl" />
                    </>
                  ) : (
                    <>
                      {/* Mobile audience card — the desktop rail carries the
                          same controls on md+. */}
                      <div className="md:hidden">{audienceSection}</div>

                      {/* The course */}
                      <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                        <div className="space-y-2">
                          <Label>
                            {t("courses.courseTitle")} <span className="text-destructive">*</span>
                          </Label>
                          <Input
                            value={title}
                            onChange={(e) => {
                              setTitle(e.target.value)
                              clearError("title")
                              touch()
                            }}
                            placeholder={t("courses.titlePlaceholder")}
                          />
                          {errors.title && <p className="text-destructive text-xs">{errors.title[0]}</p>}
                        </div>
                        <div className="space-y-2">
                          <Label>{t("courses.description")}</Label>
                          <RichTextEditor
                            value={description}
                            onChange={(next) => {
                              setDescription(next)
                              touch()
                            }}
                            onUploadingChange={(uploading) => {
                              setImgUploading(uploading)
                              if (!uploading) touch()
                            }}
                            onUploadImage={uploadImage}
                          />
                        </div>
                      </section>

                      {/* The structure */}
                      <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                          <Label>{t("courses.structure")}</Label>
                          <span className="text-xs tabular-nums text-muted-foreground">
                            {t("courses.treeCount", { modules: modules.length, lessons: lessonsTotal })}
                          </span>
                        </div>

                        {id === null ? (
                          <p className="rounded-xl border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                            {t("courses.nameFirst")}
                          </p>
                        ) : (
                          <div className="space-y-3">
                            {modules.map((module, moduleIndex) => (
                              <div key={module.id} className="overflow-hidden rounded-xl border">
                                <div className="flex items-center gap-2 border-b bg-muted/40 px-3 py-2">
                                  <span className="w-5 shrink-0 text-center text-xs font-semibold text-muted-foreground">
                                    {moduleIndex + 1}
                                  </span>
                                  {renamingModule?.id === module.id ? (
                                    <Input
                                      autoFocus
                                      value={renamingModule.title}
                                      onChange={(e) =>
                                        setRenamingModule({ id: module.id, title: e.target.value })
                                      }
                                      onBlur={() => void commitModuleRename()}
                                      onKeyDown={(e) => {
                                        if (e.key === "Enter") void commitModuleRename()
                                        if (e.key === "Escape") setRenamingModule(null)
                                      }}
                                      className="h-8 flex-1 bg-card"
                                    />
                                  ) : (
                                    <button
                                      type="button"
                                      className="group flex min-w-0 flex-1 items-center gap-1.5 text-left"
                                      onClick={() => setRenamingModule({ id: module.id, title: module.title })}
                                    >
                                      <span className="truncate text-sm font-semibold">{module.title}</span>
                                      <Pencil className="size-3 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                    </button>
                                  )}
                                  <div className="flex shrink-0 items-center">
                                    <button
                                      type="button"
                                      onClick={() => moveModule(moduleIndex, -1)}
                                      disabled={moduleIndex === 0}
                                      className="rounded p-1 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                      aria-label={t("exams.moveUp")}
                                    >
                                      <ChevronUp className="size-3.5" />
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => moveModule(moduleIndex, 1)}
                                      disabled={moduleIndex === modules.length - 1}
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
                                      onClick={() =>
                                        confirmDelete(
                                          () => removeModule(module),
                                          tc("confirmDelete.named", { name: module.title }),
                                        )
                                      }
                                      aria-label={tc("actions.delete")}
                                    >
                                      <Trash2 className="size-3.5" />
                                    </Button>
                                  </div>
                                </div>

                                {module.lessons.length === 0 ? (
                                  <p className="px-3 py-3 text-center text-xs text-muted-foreground">
                                    {t("courses.noLessons")}
                                  </p>
                                ) : (
                                  <div className="divide-y">
                                    {module.lessons.map((lesson, lessonIndex) => {
                                      const Icon = LESSON_ICONS[lesson.type] ?? BookOpen
                                      return (
                                        <div key={lesson.id} className="flex items-center gap-2.5 px-3 py-2">
                                          <Icon className="size-4 shrink-0 text-muted-foreground" />
                                          <button
                                            type="button"
                                            className="min-w-0 flex-1 text-left"
                                            onClick={() => {
                                              setLessonModuleId(module.id)
                                              setEditingLesson(lesson)
                                              setLessonOpen(true)
                                            }}
                                          >
                                            <p className="truncate text-sm">{lesson.title}</p>
                                            <p className="truncate text-xs text-muted-foreground">
                                              {t(`courses.lessonTypes.${lesson.type}`)}
                                              {lesson.duration_minutes
                                                ? ` · ${t("learn.minutes", { count: lesson.duration_minutes })}`
                                                : ""}
                                              {lesson.quiz_title ? ` · ${lesson.quiz_title}` : ""}
                                            </p>
                                          </button>
                                          <div className="flex shrink-0 items-center">
                                            <button
                                              type="button"
                                              onClick={() => moveLesson(moduleIndex, lessonIndex, -1)}
                                              disabled={lessonIndex === 0}
                                              className="rounded p-1 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                              aria-label={t("exams.moveUp")}
                                            >
                                              <ChevronUp className="size-3.5" />
                                            </button>
                                            <button
                                              type="button"
                                              onClick={() => moveLesson(moduleIndex, lessonIndex, 1)}
                                              disabled={lessonIndex === module.lessons.length - 1}
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
                                              onClick={() =>
                                                confirmDelete(
                                                  () => removeLesson(lesson),
                                                  tc("confirmDelete.named", { name: lesson.title }),
                                                )
                                              }
                                              aria-label={tc("actions.delete")}
                                            >
                                              <Trash2 className="size-3.5" />
                                            </Button>
                                          </div>
                                        </div>
                                      )
                                    })}
                                  </div>
                                )}

                                <div className="border-t px-3 py-1.5">
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-primary"
                                    onClick={() => {
                                      setLessonModuleId(module.id)
                                      setEditingLesson(null)
                                      setLessonOpen(true)
                                    }}
                                  >
                                    <Plus className="size-3.5" /> {t("courses.addLesson")}
                                  </Button>
                                </div>
                              </div>
                            ))}

                            <div className="flex items-center gap-2">
                              <Input
                                value={newModuleTitle}
                                onChange={(e) => setNewModuleTitle(e.target.value)}
                                placeholder={t("courses.newModulePlaceholder")}
                                onKeyDown={(e) => {
                                  if (e.key === "Enter") void addModule()
                                }}
                              />
                              <Button
                                type="button"
                                variant="outline"
                                className="h-10 shrink-0"
                                onClick={() => void addModule()}
                                loading={addingModule} disabled={newModuleTitle.trim() === ""}
                              >
                                <Plus className="size-4" /> {t("courses.addModule")}
                              </Button>
                            </div>
                          </div>
                        )}
                      </section>
                    </>
                  )}
                </div>
              </main>

              {/* Settings rail: audience first — it decides everything else */}
              <aside className="border-t bg-background md:min-h-0 md:w-[340px] md:shrink-0 md:overflow-y-auto md:border-l md:border-t-0">
                <div className="space-y-5 p-4 md:p-5">
                  <div className="hidden md:block">{audienceSection}</div>

                  <div className="space-y-2">
                    <Label>{t("questions.status")}</Label>
                    <Select
                      value={status}
                      onValueChange={(v) => {
                        setStatus(v as Course["status"])
                        clearError("status")
                        touch()
                      }}
                      disabled={id === null}
                    >
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="draft">{t("courses.statuses.draft")}</SelectItem>
                        <SelectItem value="published">{t("courses.statuses.published")}</SelectItem>
                        <SelectItem value="archived">{t("courses.statuses.archived")}</SelectItem>
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">{t("courses.statusHint")}</p>
                    {errors.status && <p className="text-destructive text-xs">{errors.status[0]}</p>}
                  </div>

                  <div className="space-y-2">
                    <label className="flex items-center justify-between rounded-xl border px-3.5 py-3">
                      <span className="text-sm">{t("courses.sequential")}</span>
                      <Switch
                        checked={sequential}
                        onCheckedChange={(next) => {
                          setSequential(next)
                          touch()
                        }}
                      />
                    </label>
                    <p className="text-xs text-muted-foreground">{t("courses.sequentialHint")}</p>
                  </div>

                  <div
                    {...coverDrop.dropProps}
                    className={cn("space-y-2 rounded-2xl", coverDrop.dragOver && DROP_ACTIVE)}
                  >
                    <Label>{t("courses.cover")}</Label>
                    {(cover || coverUrl) && (
                      // eslint-disable-next-line @next/next/no-img-element -- signed R2 URL / local preview
                      <img
                        src={cover ? URL.createObjectURL(cover) : (coverUrl ?? "")}
                        alt=""
                        className="h-28 w-full rounded-xl border object-cover"
                      />
                    )}
                    <input
                      ref={coverInput}
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      className="hidden"
                      onChange={(e) => {
                        coverDrop.takeFiles(e.target.files)
                        e.target.value = ""
                      }}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      className="w-full justify-start"
                      onClick={() => coverInput.current?.click()}
                    >
                      <ImageIcon className="size-4" />
                      <span className="truncate">{cover ? cover.name : t("courses.cover")}</span>
                    </Button>
                    <p className="text-xs text-muted-foreground">{t("courses.coverHint")}</p>
                    {errors.cover && <p className="text-destructive text-xs">{errors.cover[0]}</p>}
                  </div>
                </div>
              </aside>
            </div>
            {confirmDialog}
          </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
      </DialogPrimitive.Root>

      <LessonEditor
        course={{ id: id ?? 0, is_platform: platform, subject_assignment_id: targetIds[0] ?? null }}
        moduleId={lessonModuleId}
        lesson={editingLesson}
        open={lessonOpen}
        onOpenChange={(next) => {
          setLessonOpen(next)
          if (!next) setEditingLesson(null)
        }}
        onSaved={handleLessonSaved}
      />
    </>
  )
}
