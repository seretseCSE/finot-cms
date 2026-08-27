"use client"

import { FolderOpen } from "lucide-react"
import { useCallback, useEffect, useState } from "react"

import { MaterialCard } from "@/components/lms/material-card"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { MeMaterial } from "@/lib/types"

/** Course materials shared with the student's class (ADR-012 relationship lane). */
export default function MyMaterialsPage() {
  const { t } = useTranslation("lms")
  const { user } = useAuth()

  const [materials, setMaterials] = useState<MeMaterial[] | null>(null)

  const load = useCallback(() => {
    apiFetch<{ data: MeMaterial[] }>("/me/lms/materials?per_page=100")
      .then((res) => setMaterials(res.data))
      .catch(() => setMaterials((prev) => prev ?? []))
  }, [])

  useEffect(() => {
    if (!user?.is_student) return
    load()
    function onVisible() {
      if (document.visibilityState === "visible") load()
    }
    document.addEventListener("visibilitychange", onVisible)
    return () => document.removeEventListener("visibilitychange", onVisible)
  }, [user?.is_student, load])

  if (!user?.is_student) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("learn.materials")} description={t("learn.subtitle")} />
        <div className="page-gutter">
          <EmptyState icon={FolderOpen} title={t("learn.notStudent")} />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("learn.materials")} description={t("learn.materialsSubtitle")} />

      <div className="page-gutter">
        <div className="mx-auto">
          {materials === null ? (
            <div className="grid items-start gap-3 md:grid-cols-2">
              <Skeleton className="h-28 w-full rounded-2xl" />
              <Skeleton className="h-28 w-full rounded-2xl" />
            </div>
          ) : materials.length === 0 ? (
            <EmptyState
              icon={FolderOpen}
              title={t("learn.emptyMaterials")}
              description={t("learn.emptyMaterialsDesc")}
            />
          ) : (
            <div className="grid items-start gap-3 md:grid-cols-2">
              {materials.map((material) => (
                <MaterialCard key={material.id} material={material} />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
