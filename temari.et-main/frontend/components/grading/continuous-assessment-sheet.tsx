"use client"

import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core"
import {
  restrictToParentElement,
  restrictToVerticalAxis,
} from "@dnd-kit/modifiers"
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"
import { GripVertical, Plus, Trash2, TriangleAlert } from "lucide-react"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { DatePicker } from "@/components/ui/date-picker"
import { Input } from "@/components/ui/input"
import { Label, MOBILE_FIELD_LABEL } from "@/components/ui/label"
import { MultiCombobox } from "@/components/ui/multi-combobox"
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
import { TermSelect } from "@/components/academic/term-select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { subjectAppliesToSort } from "@/lib/subjects"
import type {
  ContinuousAssessment,
  ContinuousAssessmentItem,
  ContinuousAssessmentItemType,
  ContinuousAssessmentTarget,
  GradeLevel,
  Paginated,
  Section,
  Subject,
  Term,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const ITEM_TYPES: ContinuousAssessmentItemType[] = [
  "quiz",
  "test",
  "assignment",
  "project",
  "mid_exam",
  "final_exam",
]

const ALL_GRADES = "all"

type Translate = (key: string, vars?: Record<string, string | number>) => string

/** One targeting row summarised as "Grade 5 · A, B · Math, Physics". */
export function describeTarget(
  target: ContinuousAssessmentTarget,
  t: Translate
): string {
  const grade =
    target.grade_level_id === null
      ? t("continuousAssessment.allGrades")
      : (target.grade_name ?? `#${target.grade_level_id}`)
  const subjects = target.subject_names.length
    ? target.subject_names.join(", ")
    : t("continuousAssessment.allSubjects")

  // Sections only make sense under a specific grade.
  if (target.grade_level_id === null) {
    return `${grade} · ${subjects}`
  }

  const sections = target.section_names.length
    ? target.section_names.join(", ")
    : t("continuousAssessment.allSections")

  return `${grade} · ${sections} · ${subjects}`
}

const DEFAULT_ITEMS: ContinuousAssessmentItem[] = [
  { type: "quiz", name: "Quiz", weight: 10, max_score: 10, due_on: null },
  {
    type: "assignment",
    name: "Assignment",
    weight: 15,
    max_score: 15,
    due_on: null,
  },
  {
    type: "mid_exam",
    name: "Mid exam",
    weight: 25,
    max_score: 25,
    due_on: null,
  },
  {
    type: "final_exam",
    name: "Final exam",
    weight: 50,
    max_score: 50,
    due_on: null,
  },
]

/** One overlapping plan reported by the 409 conflict sheet. */
interface ConflictBook {
  id: number
  name: string
  targets: ContinuousAssessmentTarget[]
  items_count: number
  marks_count: number
}

interface PlanConflicts {
  books: ConflictBook[]
  free_form: { assessments: number; marks_count: number }
}

type ConflictStrategy = "replace" | "migrate"

/** Item + a stable client-side key so rows can be dragged around. */
type SortableItem = ContinuousAssessmentItem & { _uid: string }

const withUid = (item: ContinuousAssessmentItem): SortableItem => ({
  ...item,
  _uid: crypto.randomUUID(),
})

/** One "applies to" row: a grade (null = all) → sections → subjects. */
type TargetRow = {
  _uid: string
  gradeLevelId: number | null
  sectionIds: number[]
  subjectIds: number[]
}

const newTargetRow = (gradeLevelId: number | null = null): TargetRow => ({
  _uid: crypto.randomUUID(),
  gradeLevelId,
  sectionIds: [],
  subjectIds: [],
})

const toTargetRow = (target: ContinuousAssessmentTarget): TargetRow => ({
  _uid: crypto.randomUUID(),
  gradeLevelId: target.grade_level_id,
  sectionIds: target.section_ids ?? [],
  subjectIds: target.subject_ids ?? [],
})

/** One draggable row: grip handle + the item's fields. */
function SortableItemRow({
  id,
  handleLabel,
  children,
}: {
  id: string
  handleLabel: string
  children: React.ReactNode
}) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id })

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        "flex items-center gap-1.5 rounded-xl border bg-card p-3 sm:rounded-lg sm:border-0 sm:bg-transparent sm:p-0 sm:px-1",
        isDragging &&
          "relative z-10 border-primary/40 bg-card shadow-md sm:rounded-lg sm:border sm:p-1"
      )}
    >
      <button
        type="button"
        {...attributes}
        {...listeners}
        aria-label={handleLabel}
        className="-ml-1 flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground/60 transition-colors hover:text-foreground active:cursor-grabbing"
      >
        <GripVertical className="size-3.5" />
      </button>
      {children}
    </div>
  )
}

interface Props {
  book: ContinuousAssessment | null
  terms: Term[]
  subjects: Subject[]
  gradeLevels: GradeLevel[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}

/**
 * The continuous-assessment builder: name + term + targeting rows (grade →
 * sections → subjects) + the assessment slots whose weights must sum to
 * exactly 100. Principal/director only.
 */
export function ContinuousAssessmentSheet({
  book,
  terms,
  subjects,
  gradeLevels,
  open,
  onOpenChange,
  onSaved,
}: Props) {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()

  const [branchId, setBranchId] = useState<number | null>(null)
  const [name, setName] = useState("")
  const [termId, setTermId] = useState<string>("")
  const [targets, setTargets] = useState<TargetRow[]>([newTargetRow()])
  const [sectionsByGrade, setSectionsByGrade] = useState<
    Record<number, Section[]>
  >({})
  const [items, setItems] = useState<SortableItem[]>(DEFAULT_ITEMS.map(withUid))
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)

  // Sections belong to a grade in the target branch; fetch once per grade
  // (per selected branch when writing from the school-wide workspace).
  const loadedGrades = useRef<Set<number>>(new Set())

  const fetchSections = useCallback(
    (gradeLevelId: number) => {
      if (loadedGrades.current.has(gradeLevelId)) return
      loadedGrades.current.add(gradeLevelId)

      const params = new URLSearchParams({
        grade_level_id: String(gradeLevelId),
        per_page: "100",
      })
      const effectiveBranch = book?.branch_id ?? branchId
      if (effectiveBranch != null)
        params.set("branch_id", String(effectiveBranch))

      apiFetch<Paginated<Section>>(`/sections?${params.toString()}`)
        .then((res) =>
          setSectionsByGrade((cur) => ({ ...cur, [gradeLevelId]: res.data }))
        )
        .catch(() =>
          setSectionsByGrade((cur) => ({ ...cur, [gradeLevelId]: [] }))
        )
    },
    [book, branchId]
  )

  // Overlap confirmation (409 plan_conflict): the office picks a strategy and
  // explicitly acknowledges what happens to recorded marks.
  const [conflicts, setConflicts] = useState<PlanConflicts | null>(null)
  const [strategy, setStrategy] = useState<ConflictStrategy>("migrate")
  const [acknowledged, setAcknowledged] = useState(false)

  // Drag starts from the grip handle only; keyboard works for accessibility.
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  )

  function onDragEnd({ active, over }: DragEndEvent) {
    if (!over || active.id === over.id) return
    setItems((prev) =>
      arrayMove(
        prev,
        prev.findIndex((i) => i._uid === active.id),
        prev.findIndex((i) => i._uid === over.id)
      )
    )
  }

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- sync sheet with the edited row */
    setBranchId(book?.branch_id ?? null)
    setName(book?.name ?? "")
    setTermId(
      book
        ? String(book.term_id)
        : String(
            terms.find((x) => x.status === "active")?.id ?? terms[0]?.id ?? ""
          )
    )
    const rows = book?.targets?.length
      ? book.targets.map(toTargetRow)
      : [newTargetRow()]
    setTargets(rows)
    // Preload the sections of each targeted grade so the multi-selects can
    // render names right away on edit.
    loadedGrades.current = new Set()
    setSectionsByGrade({})
    rows.forEach(
      (row) => row.gradeLevelId !== null && fetchSections(row.gradeLevelId)
    )
    setItems(
      book?.items?.length ? book.items.map(withUid) : DEFAULT_ITEMS.map(withUid)
    )
    setErrors({})
    /* eslint-enable react-hooks/set-state-in-effect */
    // eslint-disable-next-line react-hooks/exhaustive-deps -- fetchSections is stable per book/branch; hydrate only on open
  }, [open, book, terms])

  // Switching the target branch (school-wide create) invalidates any sections
  // picked for the old branch — reset the cache and clear section choices.
  useEffect(() => {
    if (!open || book) return
    loadedGrades.current = new Set()
    /* eslint-disable react-hooks/set-state-in-effect -- branch swap resets sections */
    setSectionsByGrade({})
    setTargets((prev) => prev.map((row) => ({ ...row, sectionIds: [] })))
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [branchId, open, book])

  const totalWeight = useMemo(
    () =>
      Math.round(
        items.reduce((sum, i) => sum + (Number(i.weight) || 0), 0) * 100
      ) / 100,
    [items]
  )

  function setItem(index: number, patch: Partial<ContinuousAssessmentItem>) {
    setItems((prev) =>
      prev.map((it, i) => (i === index ? { ...it, ...patch } : it))
    )
  }

  const hasAllGradesRow = targets.some((row) => row.gradeLevelId === null)
  // Grades already spoken for (so a second row can't repeat one).
  const usedGrades = useMemo(
    () => new Set(targets.map((row) => row.gradeLevelId)),
    [targets]
  )
  const canAddGrade =
    !hasAllGradesRow && gradeLevels.some((g) => !usedGrades.has(g.id))

  function setTarget(uid: string, patch: Partial<TargetRow>) {
    setTargets((prev) =>
      prev.map((row) => (row._uid === uid ? { ...row, ...patch } : row))
    )
  }

  // Only the subjects taught at a grade (their curriculum grade window covers
  // it) — the same window that drives the semester grid. All grades → the
  // whole catalog.
  const subjectsForGrade = useCallback(
    (gradeLevelId: number | null): Subject[] => {
      if (gradeLevelId === null) return subjects
      const sort = gradeLevels.find((g) => g.id === gradeLevelId)?.sort_order
      if (sort == null) return subjects
      return subjects.filter((s) => subjectAppliesToSort(s, sort))
    },
    [subjects, gradeLevels]
  )

  function setTargetGrade(uid: string, value: string) {
    const gradeLevelId = value === ALL_GRADES ? null : Number(value)
    // Grade changed → sections no longer apply, and keep only the subjects
    // still offered at the new grade.
    const allowed = new Set(subjectsForGrade(gradeLevelId).map((s) => s.id))
    setTargets((prev) =>
      prev.map((row) =>
        row._uid === uid
          ? {
              ...row,
              gradeLevelId,
              sectionIds: [],
              subjectIds: row.subjectIds.filter((id) => allowed.has(id)),
            }
          : row
      )
    )
    if (gradeLevelId !== null) fetchSections(gradeLevelId)
  }

  function addTargetRow() {
    const nextGrade = gradeLevels.find((g) => !usedGrades.has(g.id))
    if (!nextGrade) return
    fetchSections(nextGrade.id)
    setTargets((prev) => [...prev, newTargetRow(nextGrade.id)])
  }

  function removeTargetRow(uid: string) {
    setTargets((prev) =>
      prev.length <= 1 ? prev : prev.filter((row) => row._uid !== uid)
    )
  }

  async function save(confirmedStrategy?: ConflictStrategy) {
    setSaving(true)
    setErrors({})
    try {
      const body = {
        ...(branchId != null && !book ? { branch_id: branchId } : {}),
        term_id: Number(termId),
        name,
        targets: targets.map((row) => ({
          grade_level_id: row.gradeLevelId,
          section_ids:
            row.gradeLevelId !== null && row.sectionIds.length
              ? row.sectionIds
              : null,
          subject_ids: row.subjectIds.length ? row.subjectIds : null,
        })),
        ...(confirmedStrategy ? { conflict_strategy: confirmedStrategy } : {}),
        items: items.map((i) => ({
          ...(i.id ? { id: i.id } : {}),
          type: i.type,
          name: i.name,
          weight: Number(i.weight),
          max_score: Number(i.max_score),
          due_on: i.due_on,
        })),
      }
      await apiFetch(
        book ? `/continuous-assessments/${book.id}` : "/continuous-assessments",
        {
          method: book ? "PUT" : "POST",
          body,
        }
      )
      setConflicts(null)
      toast.success(t("continuousAssessment.saved"))
      onOpenChange(false)
      onSaved()
    } catch (error) {
      if (
        error instanceof ApiError &&
        error.status === 409 &&
        error.code === "plan_conflict"
      ) {
        // Overlap found — ask which way to resolve it before saving again.
        setStrategy("migrate")
        setAcknowledged(false)
        setConflicts(error.payload.conflicts as unknown as PlanConflicts)
      } else if (error instanceof ApiError && error.errors) {
        setErrors(error.errors)
        toast.error(error.message)
      } else {
        toast.error(
          error instanceof ApiError ? error.message : tc("errors.generic")
        )
      }
    } finally {
      setSaving(false)
    }
  }

  const fieldError = (key: string) => errors[key]?.[0] ?? null
  const submitBlocked =
    saving ||
    !name ||
    !termId ||
    totalWeight !== 100 ||
    targets.length === 0 ||
    items.some((i) => !i.name) ||
    (needsBranch && !book && branchId === null)

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-4xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {book
              ? t("continuousAssessment.edit")
              : t("continuousAssessment.add")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          {!book && <BranchField value={branchId} onChange={setBranchId} />}

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>
                {t("continuousAssessment.name")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} />
              {fieldError("name") && (
                <p className="text-xs text-destructive">{fieldError("name")}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label>
                {t("continuousAssessment.term")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <TermSelect
                terms={terms}
                value={termId}
                onValueChange={setTermId}
                disabled={!!book}
                className="w-full"
                emptyNotice={tc("emptySelect.terms")}
              />
              {fieldError("term_id") && (
                <p className="text-xs text-destructive">
                  {fieldError("term_id")}
                </p>
              )}
            </div>
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <Label>{t("continuousAssessment.appliesTo")}</Label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addTargetRow}
                disabled={!canAddGrade}
              >
                <Plus className="size-3.5" />
                {t("continuousAssessment.addGrade")}
              </Button>
            </div>

            <div className="hidden items-center gap-2 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1.3fr)_2rem]">
              <span>{t("continuousAssessment.grade")}</span>
              <span>{t("continuousAssessment.sections")}</span>
              <span>{t("continuousAssessment.subjects")}</span>
              <span />
            </div>

            <div className="space-y-2">
              {targets.map((row) => {
                const subjectOptions = subjectsForGrade(row.gradeLevelId).map(
                  (s) => ({
                    value: s.id,
                    label: s.code ? `${s.name} (${s.code})` : s.name,
                  })
                )
                const sectionOptions = (
                  row.gradeLevelId !== null
                    ? (sectionsByGrade[row.gradeLevelId] ?? [])
                    : []
                ).map((s) => ({ value: s.id, label: s.name }))

                return (
                  <div
                    key={row._uid}
                    className="grid grid-cols-1 gap-2 rounded-xl border bg-card p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1.3fr)_2rem] sm:items-center sm:rounded-lg sm:border-0 sm:bg-transparent sm:p-0 sm:px-1"
                  >
                    <div className="min-w-0 space-y-1 sm:contents">
                      <span className={MOBILE_FIELD_LABEL}>
                        {t("continuousAssessment.grade")}
                      </span>
                      <Select
                        value={
                          row.gradeLevelId === null
                            ? ALL_GRADES
                            : String(row.gradeLevelId)
                        }
                        onValueChange={(v) => setTargetGrade(row._uid, v)}
                      >
                        <SelectTrigger
                          className="w-full"
                          aria-label={t("continuousAssessment.grade")}
                        >
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem
                            value={ALL_GRADES}
                            disabled={targets.length > 1}
                          >
                            {t("continuousAssessment.allGrades")}
                          </SelectItem>
                          {gradeLevels.map((g) => (
                            <SelectItem
                              key={g.id}
                              value={String(g.id)}
                              disabled={
                                usedGrades.has(g.id) &&
                                row.gradeLevelId !== g.id
                              }
                            >
                              {g.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="min-w-0 space-y-1 sm:contents">
                      <span className={MOBILE_FIELD_LABEL}>
                        {t("continuousAssessment.sections")}
                      </span>
                      <MultiCombobox
                        options={sectionOptions}
                        value={row.sectionIds}
                        onChange={(v) => setTarget(row._uid, { sectionIds: v })}
                        allLabel={t("continuousAssessment.allSections")}
                        searchPlaceholder={tc("actions.search")}
                        emptyText={tc("search.empty")}
                        disabled={row.gradeLevelId === null}
                      />
                    </div>

                    <div className="min-w-0 space-y-1 sm:contents">
                      <span className={MOBILE_FIELD_LABEL}>
                        {t("continuousAssessment.subjects")}
                      </span>
                      <MultiCombobox
                        options={subjectOptions}
                        value={row.subjectIds}
                        onChange={(v) => setTarget(row._uid, { subjectIds: v })}
                        allLabel={t("continuousAssessment.allSubjects")}
                        searchPlaceholder={tc("actions.search")}
                        emptyText={tc("search.empty")}
                      />
                    </div>

                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="size-8 shrink-0 justify-self-end text-muted-foreground hover:text-destructive"
                      disabled={targets.length <= 1}
                      onClick={() => removeTargetRow(row._uid)}
                      aria-label={tc("actions.delete")}
                    >
                      <Trash2 className="size-3.5" />
                    </Button>
                  </div>
                )
              })}
            </div>

            {fieldError("targets") && (
              <p className="text-xs text-destructive">
                {fieldError("targets")}
              </p>
            )}
            <p className="text-xs text-muted-foreground">
              {t("continuousAssessment.appliesToHint")}
            </p>
          </div>

          <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
              <Label>{t("continuousAssessment.items")}</Label>
              <div className="flex items-center gap-2">
                <span
                  className={cn(
                    "rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap tabular-nums",
                    totalWeight === 100
                      ? "bg-success/10 text-success"
                      : "bg-destructive/10 text-destructive"
                  )}
                >
                  {t("continuousAssessment.totalWeight")}: {totalWeight}%
                </span>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() =>
                    setItems((prev) => [
                      ...prev,
                      withUid({
                        type: "quiz",
                        name: "",
                        weight: 0,
                        max_score: 10,
                        due_on: null,
                      }),
                    ])
                  }
                >
                  <Plus className="size-3.5" />
                  {t("continuousAssessment.addItem")}
                </Button>
              </div>
            </div>
            {totalWeight !== 100 && (
              <p className="text-xs text-muted-foreground">
                {t("continuousAssessment.weightHint")}
              </p>
            )}
            {fieldError("items") && (
              <p className="text-xs text-destructive">{fieldError("items")}</p>
            )}

            <div className="space-y-2">
              <div className="hidden items-center gap-1.5 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:flex">
                <span className="w-6 shrink-0" />
                <div className="grid flex-1 grid-cols-[1.1fr_1.3fr_5rem_5rem_14rem_2rem] gap-2">
                  <span>{t("continuousAssessment.itemType")}</span>
                  <span>{t("continuousAssessment.itemName")}</span>
                  <span>{t("continuousAssessment.itemWeight")}</span>
                  <span>{t("continuousAssessment.itemMax")}</span>
                  <span>{t("continuousAssessment.itemDue")}</span>
                  <span />
                </div>
              </div>
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                modifiers={[restrictToVerticalAxis, restrictToParentElement]}
                onDragEnd={onDragEnd}
              >
                <SortableContext
                  items={items.map((item) => item._uid)}
                  strategy={verticalListSortingStrategy}
                >
                  {items.map((item, i) => (
                    <SortableItemRow
                      key={item._uid}
                      id={item._uid}
                      handleLabel={t("continuousAssessment.reorder")}
                    >
                      <div className="grid min-w-0 flex-1 grid-cols-2 gap-2 sm:grid-cols-[1.1fr_1.3fr_5rem_5rem_14rem_2rem] sm:items-center">
                        <div className="min-w-0 space-y-1 sm:contents">
                          <span className={MOBILE_FIELD_LABEL}>
                            {t("continuousAssessment.itemType")}
                          </span>
                          <Select
                            value={item.type}
                            onValueChange={(v) =>
                              setItem(i, {
                                type: v as ContinuousAssessmentItemType,
                              })
                            }
                          >
                            <SelectTrigger
                              className="w-full"
                              aria-label={t("continuousAssessment.itemType")}
                            >
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              {ITEM_TYPES.map((type) => (
                                <SelectItem key={type} value={type}>
                                  {t(`types.${type}`)}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                        <div className="min-w-0 space-y-1 sm:contents">
                          <span className={MOBILE_FIELD_LABEL}>
                            {t("continuousAssessment.itemName")}
                          </span>
                          <Input
                            aria-label={t("continuousAssessment.itemName")}
                            value={item.name}
                            onChange={(e) =>
                              setItem(i, { name: e.target.value })
                            }
                          />
                        </div>
                        <div className="min-w-0 space-y-1 sm:contents">
                          <span className={MOBILE_FIELD_LABEL}>
                            {t("continuousAssessment.itemWeight")}
                          </span>
                          <Input
                            type="number"
                            inputMode="decimal"
                            className="no-spinner px-2.5 text-center tabular-nums"
                            aria-label={t("continuousAssessment.itemWeight")}
                            value={item.weight}
                            onChange={(e) =>
                              setItem(i, { weight: Number(e.target.value) })
                            }
                          />
                        </div>
                        <div className="min-w-0 space-y-1 sm:contents">
                          <span className={MOBILE_FIELD_LABEL}>
                            {t("continuousAssessment.itemMax")}
                          </span>
                          <Input
                            type="number"
                            inputMode="decimal"
                            className="no-spinner px-2.5 text-center tabular-nums"
                            aria-label={t("continuousAssessment.itemMax")}
                            value={item.max_score}
                            onChange={(e) =>
                              setItem(i, { max_score: Number(e.target.value) })
                            }
                          />
                        </div>
                        {/* Mobile: due date + delete share a full-width row so
                            dates like "September 30, 2026" aren't half-clipped.
                            Desktop: sm:contents lets both join the 6-col grid. */}
                        <div className="col-span-2 min-w-0 space-y-1 sm:contents">
                          <span className={MOBILE_FIELD_LABEL}>
                            {t("continuousAssessment.itemDue")}
                          </span>
                          <div className="flex min-w-0 items-center gap-2 sm:contents">
                            <DatePicker
                              value={item.due_on}
                              onChange={(v) =>
                                setItem(i, { due_on: v || null })
                              }
                              className="min-w-0 flex-1 sm:flex-none"
                            />
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                              disabled={items.length <= 1}
                              onClick={() =>
                                setItems((prev) =>
                                  prev.filter((_, x) => x !== i)
                                )
                              }
                              aria-label={tc("actions.delete")}
                            >
                              <Trash2 className="size-3.5" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    </SortableItemRow>
                  ))}
                </SortableContext>
              </DndContext>
            </div>

            <p className="text-xs text-muted-foreground">
              {t("continuousAssessment.planLocked")}
            </p>
          </div>
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
            disabled={saving}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            className="h-11 flex-1"
            onClick={() => save()}
            loading={saving}
            disabled={submitBlocked}
          >
            {tc("actions.save")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>

      {/* Overlap confirmation — replace (start fresh) vs migrate (keep
          matching marks). Confirming requires an explicit acknowledgement. */}
      <AlertDialog
        open={conflicts !== null}
        onOpenChange={(v) => !v && setConflicts(null)}
      >
        <AlertDialogContent className="sm:max-w-lg">
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <TriangleAlert className="size-5 shrink-0 text-warning" />
              {t("continuousAssessment.conflict.title")}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("continuousAssessment.conflict.intro")}
            </AlertDialogDescription>
          </AlertDialogHeader>

          <div className="max-h-40 space-y-2 overflow-y-auto">
            {conflicts?.books.map((b) => (
              <div
                key={b.id}
                className="rounded-xl border bg-muted/30 px-3 py-2"
              >
                <p className="text-sm font-medium">{b.name}</p>
                <p className="text-xs text-muted-foreground">
                  {b.targets.map((tg) => describeTarget(tg, t)).join(" | ")} ·{" "}
                  {t("continuousAssessment.conflict.itemsCount", {
                    count: b.items_count,
                  })}
                  {b.marks_count > 0 && (
                    <span className="font-medium text-destructive">
                      {" · "}
                      {t("continuousAssessment.conflict.marksCount", {
                        count: b.marks_count,
                      })}
                    </span>
                  )}
                </p>
              </div>
            ))}
            {(conflicts?.free_form.assessments ?? 0) > 0 && (
              <div className="rounded-xl border bg-muted/30 px-3 py-2">
                <p className="text-sm font-medium">
                  {t("continuousAssessment.conflict.freeForm", {
                    count: conflicts?.free_form.assessments ?? 0,
                  })}
                </p>
                {(conflicts?.free_form.marks_count ?? 0) > 0 && (
                  <p className="text-xs font-medium text-destructive">
                    {t("continuousAssessment.conflict.marksCount", {
                      count: conflicts?.free_form.marks_count ?? 0,
                    })}
                  </p>
                )}
              </div>
            )}
          </div>

          <div className="grid gap-2">
            {(["migrate", "replace"] as const).map((option) => {
              const selected = strategy === option
              return (
                <button
                  key={option}
                  type="button"
                  onClick={() => setStrategy(option)}
                  className={cn(
                    "rounded-xl border p-3 text-left transition-colors",
                    selected
                      ? option === "replace"
                        ? "border-destructive/50 bg-destructive/5"
                        : "border-primary bg-primary/5"
                      : "hover:bg-muted/50"
                  )}
                  aria-pressed={selected}
                >
                  <p
                    className={cn(
                      "text-sm font-medium",
                      selected && option === "replace" && "text-destructive"
                    )}
                  >
                    {t(`continuousAssessment.conflict.${option}`)}
                  </p>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {t(`continuousAssessment.conflict.${option}Hint`)}
                  </p>
                </button>
              )
            })}
          </div>

          <label className="flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-2.5">
            <Checkbox
              checked={acknowledged}
              onCheckedChange={(v) => setAcknowledged(v === true)}
              className="mt-0.5"
            />
            <span className="text-xs leading-relaxed">
              {strategy === "replace"
                ? t("continuousAssessment.conflict.ackReplace")
                : t("continuousAssessment.conflict.ackMigrate")}
            </span>
          </label>

          <AlertDialogFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setConflicts(null)}
              disabled={saving}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              variant={strategy === "replace" ? "destructive" : "default"}
              className="h-11 flex-1"
              loading={saving}
              disabled={!acknowledged}
              onClick={() => save(strategy)}
            >
              {t("continuousAssessment.conflict.confirm")}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </ResponsiveSheet>
  )
}
