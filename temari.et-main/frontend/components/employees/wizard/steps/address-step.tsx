"use client"

import type { EmployeeFormValues } from "@/components/employees/wizard/schema"
import { AddressFields } from "@/components/ui/address-fields"
import { cn } from "@/lib/utils"

/**
 * Where the person lives. The whole step is the shared address block, which
 * reads the form from context — hence no `form` prop.
 */
export function AddressStep({ active }: { active: boolean }) {
  return (
    <div className={cn("space-y-4", !active && "hidden")}>
      <AddressFields<EmployeeFormValues> />
    </div>
  )
}
