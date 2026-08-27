"use client"

import { useParams, useSearchParams } from "next/navigation"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { EmployeeWizard } from "@/components/employees/wizard/employee-wizard"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { Employee } from "@/lib/types"

export default function EditEmployeePage() {
  const params = useParams<{ id: string }>()
  const employeeId = Number(params.id)
  // Deep link from the profile tabs (?step=positions | teaching | …).
  const initialStep = useSearchParams().get("step") ?? undefined
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const permissions = useEffectivePermissions()

  const [employee, setEmployee] = useState<Employee | null>(null)
  const canEdit = permissions.includes("employees.update")

  useEffect(() => {
    if (!canEdit) return
    apiFetch<{ data: Employee }>(`/employees/${employeeId}`)
      .then((res) => setEmployee(res.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic")),
      )
  }, [employeeId, canEdit, tc])

  if (!canEdit) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("wizard.editSubtitle")} backHref="/employees" backLabel={t("title")} />
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {tc("errors.forbidden")}
          </div>
        </div>
      </div>
    )
  }

  if (employee === null) {
    return (
      <div className="space-y-6">
        <PageHeader title={<Skeleton className="h-8 w-48" />} backHref="/employees" backLabel={t("title")} />
        <div className="page-gutter space-y-3">
          <Skeleton className="h-8 w-2/3 rounded-full" />
          <Skeleton className="h-96 w-full rounded-2xl" />
        </div>
      </div>
    )
  }

  return <EmployeeWizard employee={employee} initialStep={initialStep} />
}
