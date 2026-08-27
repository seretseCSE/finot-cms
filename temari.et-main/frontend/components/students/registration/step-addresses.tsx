"use client"

import { Copy } from "lucide-react"
import type { UseFormReturn } from "react-hook-form"

import { AddressFields } from "@/components/ui/address-fields"
import { Button } from "@/components/ui/button"
import { useTranslation } from "@/lib/i18n"

import type { RegistrationValues } from "./schema"

interface Props {
  form: UseFormReturn<RegistrationValues>
}

export function StepAddresses({ form }: Props) {
  const { t } = useTranslation("students")

  const copyBirthplace = () => {
    const values = form.getValues()
    form.setValue("state", values.birth_state ?? "")
    form.setValue("city", values.birth_city ?? "")
    form.setValue("sub_city", values.birth_sub_city ?? "")
    form.setValue("woreda", values.birth_woreda ?? "")
  }

  return (
    <div className="space-y-6">
      <section className="space-y-3">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          {t("wizard.birthplace")}
        </h3>
        <AddressFields<RegistrationValues> prefix="birth_" withHouseNo={false} />
      </section>

      <section className="space-y-3">
        <div className="flex items-center justify-between">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {t("wizard.currentAddress")}
          </h3>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-8 rounded-full px-3 text-xs"
            onClick={copyBirthplace}
          >
            <Copy className="size-3.5" />
            {t("wizard.sameAsBirthplace")}
          </Button>
        </div>
        <AddressFields<RegistrationValues> />
      </section>
    </div>
  )
}
