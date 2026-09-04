"use client"

import { Home, MapPin } from "lucide-react"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { useTranslation } from "@/lib/i18n"
import type { Student } from "@/lib/types"

function formatAddress(parts: (string | null | undefined)[]): string {
  return parts.filter(Boolean).join(", ")
}

/** Birthplace + current residence, moved off the overview into their own tab. */
export function AddressTab({ student }: { student: Student }) {
  const { t } = useTranslation("students")
  // The canonical address vocabulary (state/city/sub-city/woreda) lives with
  // the branches domain — reuse it instead of duplicating keys.
  const { t: ts } = useTranslation("schools")

  const birthplace = formatAddress([
    student.birth_woreda,
    student.birth_sub_city,
    student.birth_city,
    student.birth_state,
  ])
  const currentAddress = formatAddress([
    student.house_no,
    student.woreda,
    student.sub_city,
    student.city,
    student.state,
  ])

  const blocks = [
    {
      icon: MapPin,
      label: t("wizard.birthplace"),
      value: birthplace,
      rows: [
        [ts("branches.state"), student.birth_state],
        [ts("branches.city"), student.birth_city],
        [ts("branches.subCity"), student.birth_sub_city],
        [ts("branches.woreda"), student.birth_woreda],
      ] as const,
    },
    {
      icon: Home,
      label: t("wizard.currentAddress"),
      value: currentAddress,
      rows: [
        [ts("branches.state"), student.state],
        [ts("branches.city"), student.city],
        [ts("branches.subCity"), student.sub_city],
        [ts("branches.woreda"), student.woreda],
        [ts("branches.houseNo"), student.house_no],
      ] as const,
    },
  ]

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      {blocks.map(({ icon: Icon, label, value, rows }) => (
        <Card key={label}>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <span className="flex size-8 items-center justify-center rounded-lg bg-accent text-muted-foreground">
                <Icon className="size-4" />
              </span>
              {label}
            </CardTitle>
          </CardHeader>
          <CardContent className="text-sm">
            {value === "" ? (
              <p className="text-muted-foreground">—</p>
            ) : (
              <dl className="space-y-2">
                {rows
                  .filter(([, v]) => Boolean(v))
                  .map(([rowLabel, rowValue]) => (
                    <div key={rowLabel} className="flex items-baseline justify-between gap-4">
                      <dt className="text-xs text-muted-foreground">{rowLabel}</dt>
                      <dd className="font-medium">{rowValue}</dd>
                    </div>
                  ))}
              </dl>
            )}
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
