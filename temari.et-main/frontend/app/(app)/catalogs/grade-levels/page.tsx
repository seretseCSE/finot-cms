"use client"

import { Layers } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { CatalogPillNav, useCatalogs } from "@/components/catalogs/catalogs-shell"
import { GradeLevelGrid } from "@/components/catalogs/grade-level-grid"
import { GradeLevelSheet } from "@/components/catalogs/grade-level-sheet"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { GradeLevel } from "@/lib/types"

/**
 * The national grade ladder (KG-1 … Grade 12) as a spreadsheet-style editor:
 * drag rows to reorder, edit code/name inline, pick the cycle from a dropdown
 * and flip the national-exam switch — no more opening a sheet per row.
 */
export default function CatalogGradeLevelsPage() {
  const { t } = useTranslation("catalogs")
  const { refreshOverview } = useCatalogs()

  const [levels, setLevels] = useState<GradeLevel[] | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)

  const load = useCallback(() => {
    apiFetch<{ data: GradeLevel[] }>("/catalogs/grade-levels")
      .then((res) => setLevels(res.data))
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : t("loadFailed"))
        setLevels([])
      })
  }, [t])

  useEffect(() => {
    load()
  }, [load])

  function handleMutated() {
    load()
    refreshOverview()
  }

  const nextSortOrder = (levels ?? []).reduce((max, l) => Math.max(max, l.sort_order), 0) + 1

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("items.gradeLevels.title")}
        description={t("items.gradeLevels.description")}
        backHref="/catalogs"
        backLabel={t("title")}
        actions={
          <GradeLevelSheet
            gradeLevel={null}
            nextSortOrder={nextSortOrder}
            open={sheetOpen}
            onOpenChange={setSheetOpen}
            onSaved={handleMutated}
            showTrigger
          />
        }
      />
      <div className="lg:hidden">
        <CatalogPillNav />
      </div>

      <div className="page-gutter">
        {levels === null ? (
          <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-11 rounded-xl" />
            ))}
          </div>
        ) : levels.length === 0 ? (
          <EmptyState
            icon={Layers}
            title={t("gradeLevels.emptyTitle")}
            description={t("gradeLevels.emptyDescription")}
          />
        ) : (
          <GradeLevelGrid levels={levels} onMutated={handleMutated} />
        )}
      </div>
    </div>
  )
}
