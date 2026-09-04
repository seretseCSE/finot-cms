"use client"

import { EmployeeWizard } from "@/components/employees/wizard/employee-wizard"
import { PageHeader } from "@/components/ui/page-header"
import { useBranchScope } from "@/components/ui/branch-select"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"

export default function NewEmployeePage() {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const { needsBranch } = useBranchScope()
  const permissions = useEffectivePermissions()

  const canCreate = permissions.includes("employees.create")
  const hasBranch = active.branchId != null

  if (!canCreate || (!hasBranch && !needsBranch)) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("wizard.title")} backHref="/employees" backLabel={t("title")} />
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {!canCreate ? tc("errors.forbidden") : t("noBranch")}
          </div>
        </div>
      </div>
    )
  }

  return <EmployeeWizard />
}
