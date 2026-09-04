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
import { restrictToParentElement, restrictToVerticalAxis } from "@dnd-kit/modifiers"
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"
import { GraduationCap, GripVertical, Loader2, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { CYCLES } from "@/components/catalogs/grade-level-sheet"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"
import type { Cycle, GradeLevel } from "@/lib/types"

/** Columns share one grid template so header and rows line up like a spreadsheet. */
const GRID_COLS =
  "grid-cols-[2rem_2.5rem_minmax(4.5rem,7rem)_minmax(8rem,1fr)_minmax(9rem,11rem)_5.5rem_5rem_2rem]"

interface Props {
  levels: GradeLevel[]
  /** Called after any mutation so the parent can refresh catalog overview counts. */
  onMutated: () => void
}

/**
 * Spreadsheet-style editor for the national grade ladder: drag rows to reorder
 * (persists sort_order atomically), edit code/name inline, pick the cycle from a
 * dropdown and flip the national-exam switch — each cell autosaves on commit.
 */
export function GradeLevelGrid({ levels, onMutated }: Props) {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [rows, setRows] = useState<GradeLevel[]>(levels)
  const [busy, setBusy] = useState<Set<number>>(new Set())

  // Mirror server state whenever the parent reloads. Optimistic edits already
  // match what the parent re-fetches, so a re-sync is a no-op in the common case.
  useEffect(() => {
    /* eslint-disable-next-line react-hooks/set-state-in-effect -- sync grid with the reloaded ladder */
    setRows(levels)
  }, [levels])

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  function mark(id: number, on: boolean) {
    setBusy((prev) => {
      const next = new Set(prev)
      if (on) next.add(id)
      else next.delete(id)
      return next
    })
  }

  /** PUT the full row (the update endpoint validates every field). */
  async function saveField(row: GradeLevel, patch: Partial<GradeLevel>) {
    const next = { ...row, ...patch }
    setRows((prev) => prev.map((r) => (r.id === row.id ? next : r)))
    mark(row.id, true)
    try {
      await apiFetch(`/catalogs/grade-levels/${row.id}`, {
        method: "PUT",
        body: {
          code: next.code,
          name: next.name,
          cycle: next.cycle,
          sort_order: next.sort_order,
          has_national_exam: next.has_national_exam,
        },
      })
      onMutated()
    } catch (error) {
      setRows((prev) => prev.map((r) => (r.id === row.id ? row : r))) // revert
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      mark(row.id, false)
    }
  }

  async function onDragEnd({ active, over }: DragEndEvent) {
    if (!over || active.id === over.id) return
    const from = rows.findIndex((r) => r.id === active.id)
    const to = rows.findIndex((r) => r.id === over.id)
    if (from < 0 || to < 0) return

    const previous = rows
    const reordered = arrayMove(rows, from, to).map((r, i) => ({ ...r, sort_order: i + 1 }))
    setRows(reordered)
    setBusy(new Set(reordered.map((r) => r.id)))
    try {
      await apiFetch("/catalogs/grade-levels/reorder", {
        method: "PUT",
        body: { ids: reordered.map((r) => r.id) },
      })
      toast.success(t("gradeLevels.reordered"))
      onMutated()
    } catch (error) {
      setRows(previous)
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(new Set())
    }
  }

  async function handleDelete(row: GradeLevel) {
    mark(row.id, true)
    try {
      await apiFetch(`/catalogs/grade-levels/${row.id}`, { method: "DELETE" })
      setRows((prev) => prev.filter((r) => r.id !== row.id))
      toast.success(t("gradeLevels.deleted"))
      onMutated()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      mark(row.id, false)
    }
  }

  return (
    <div className="space-y-2">
      {confirmDialog}
      <p className="px-1 text-xs text-muted-foreground">{t("gradeLevels.gridHint")}</p>
      <div className="overflow-x-auto rounded-2xl border bg-card shadow-xs">
        <div className="min-w-[46rem]">
          {/* Header */}
          <div
            className={cn(
              "grid items-center gap-2 border-b bg-muted/40 px-3 py-2.5 text-xs font-semibold text-muted-foreground",
              GRID_COLS,
            )}
          >
            <span />
            <span className="text-center" title={t("gradeLevels.sortOrder")}>
              #
            </span>
            <span>{t("fields.code")}</span>
            <span>{t("fields.name")}</span>
            <span>{t("gradeLevels.cycle")}</span>
            <span className="text-center">{t("gradeLevels.nationalExamShort")}</span>
            <span className="text-right">{t("columns.usage")}</span>
            <span />
          </div>

          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            modifiers={[restrictToVerticalAxis, restrictToParentElement]}
            onDragEnd={onDragEnd}
          >
            <SortableContext items={rows.map((r) => r.id)} strategy={verticalListSortingStrategy}>
              {rows.map((row) => (
                <GradeRow
                  key={row.id}
                  row={row}
                  saving={busy.has(row.id)}
                  onField={(patch) => saveField(row, patch)}
                  onDelete={() =>
                    confirmDelete(
                      () => handleDelete(row),
                      tc("confirmDelete.named", { name: row.name }),
                    )
                  }
                />
              ))}
            </SortableContext>
          </DndContext>
        </div>
      </div>
    </div>
  )
}

function GradeRow({
  row,
  saving,
  onField,
  onDelete,
}: {
  row: GradeLevel
  saving: boolean
  onField: (patch: Partial<GradeLevel>) => void
  onDelete: () => void
}) {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: row.id,
  })

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        "grid items-center gap-2 border-b px-3 py-1.5 last:border-b-0",
        GRID_COLS,
        isDragging && "relative z-10 rounded-lg border bg-card shadow-md",
        saving && "opacity-70",
      )}
    >
      <button
        type="button"
        {...attributes}
        {...listeners}
        aria-label={t("gradeLevels.dragHandle")}
        className="flex size-7 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground/60 transition-colors hover:text-foreground active:cursor-grabbing"
      >
        <GripVertical className="size-4" />
      </button>

      <span className="text-center font-mono text-xs tabular-nums text-muted-foreground">
        {saving ? (
          <Loader2 className="mx-auto size-3.5 animate-spin" />
        ) : (
          row.sort_order
        )}
      </span>

      <EditableCell
        value={row.code}
        className="font-mono uppercase"
        onCommit={(v) => {
          const code = v.trim().toUpperCase()
          if (code && code !== row.code) onField({ code })
        }}
      />

      <EditableCell
        value={row.name}
        className="font-medium"
        onCommit={(v) => {
          const name = v.trim()
          if (name && name !== row.name) onField({ name })
        }}
      />

      <Select
        value={row.cycle}
        onValueChange={(v) => v !== row.cycle && onField({ cycle: v as Cycle })}
      >
        <SelectTrigger className="h-8! w-full rounded-lg border-transparent bg-transparent px-2.5 hover:bg-muted/60 focus-visible:bg-background">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {CYCLES.map((c) => (
            <SelectItem key={c} value={c}>
              {t(`gradeLevels.cycles.${c}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <div className="flex justify-center">
        <Switch
          checked={row.has_national_exam}
          onCheckedChange={(v) => onField({ has_national_exam: v })}
          aria-label={t("gradeLevels.nationalExam")}
        />
        {row.has_national_exam && (
          <GraduationCap className="ml-1.5 size-3.5 self-center text-info" aria-hidden />
        )}
      </div>

      <span className="text-right text-xs tabular-nums text-muted-foreground">
        {row.sections_count ?? 0}
      </span>

      <Button
        variant="ghost"
        size="icon"
        className="size-7 text-muted-foreground hover:text-destructive"
        onClick={onDelete}
        aria-label={tc("actions.delete")}
      >
        <Trash2 className="size-4" />
      </Button>
    </div>
  )
}

/** A text cell that reads like plain text until focused, committing on blur/Enter. */
function EditableCell({
  value,
  className,
  onCommit,
}: {
  value: string
  className?: string
  onCommit: (value: string) => void
}) {
  const [draft, setDraft] = useState(value)
  const [focused, setFocused] = useState(false)

  // Keep the draft in sync with server state while not being edited.
  useEffect(() => {
    /* eslint-disable-next-line react-hooks/set-state-in-effect -- reflect server value when idle */
    if (!focused) setDraft(value)
  }, [value, focused])

  return (
    <Input
      value={draft}
      onChange={(e) => setDraft(e.target.value)}
      onFocus={() => setFocused(true)}
      onBlur={() => {
        setFocused(false)
        onCommit(draft)
      }}
      onKeyDown={(e) => {
        if (e.key === "Enter") e.currentTarget.blur()
        if (e.key === "Escape") {
          setDraft(value)
          e.currentTarget.blur()
        }
      }}
      className={cn(
        "h-8 border-transparent bg-transparent px-2 text-sm shadow-none hover:bg-muted/60 focus-visible:border-input focus-visible:bg-background",
        className,
      )}
    />
  )
}
