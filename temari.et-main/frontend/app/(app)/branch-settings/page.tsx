"use client"

import { useState } from "react"

import { BranchSettingsPanel } from "@/components/schools/branch-settings-panel"
import { BranchScopePicker, useBranchScope } from "@/components/ui/branch-select"
import { PageHeader } from "@/components/ui/page-header"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"

/**
 * The standalone Branch settings page — the lane for branch staff (directors)
 * who work inside ONE branch and don't hold Branch Management. School managers
 * reach the same panel through the Settings tab on /branches/[id].
 */
export default function BranchSettingsPage() {
  const { t } = useTranslation("schools")
  const { active } = useSchoolContext()
  const { needsBranch } = useBranchScope()

  const [pickedBranchId, setPickedBranchId] = useState<number | null>(null)
  const branchId = active.branchId ?? pickedBranchId

  return (
    <div className="space-y-6 pb-24">
      <PageHeader
        title={t("branchSettings.title")}
        description={t("branchSettings.subtitle")}
        actions={
          // Principals in the school-wide workspace pick the branch here.
          needsBranch ? (
            <BranchScopePicker value={pickedBranchId} onChange={setPickedBranchId} />
          ) : undefined
        }
      />

      <div className="page-gutter">
        <div className="mx-auto max-w-3xl">
          {!branchId ? (
            <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
              {t("branchSettings.pickBranch")}
            </div>
          ) : (
            <BranchSettingsPanel branchId={branchId} />
          )}
        </div>
      </div>
    </div>
  )
}
