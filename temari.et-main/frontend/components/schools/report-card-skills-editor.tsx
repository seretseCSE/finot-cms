"use client"

import { Plus, X } from "lucide-react"
import { useMemo } from "react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { SUGGESTED_REPORT_CARD_SKILLS } from "@/lib/report-card-skills"
import { useTranslation } from "@/lib/i18n"
import type { ReportCardSkill } from "@/lib/types"

const MAX_SKILLS = 30

/**
 * The behavioral skill-checklist builder for report-card settings: one-tap
 * additions from the suggested Ethiopian catalog plus fully custom rows,
 * each with tri-language labels and a habits/character grouping. The list is
 * school content — removing a row here only edits the draft; nothing
 * persists until the surrounding settings card saves.
 */
export function ReportCardSkillsEditor({
  value,
  onChange,
  disabled,
}: {
  value: ReportCardSkill[]
  onChange: (skills: ReportCardSkill[]) => void
  disabled?: boolean
}) {
  const { t } = useTranslation("schools")

  const remainingSuggestions = useMemo(
    () => SUGGESTED_REPORT_CARD_SKILLS.filter((s) => !value.some((v) => v.key === s.key)),
    [value],
  )

  function patch(index: number, next: Partial<ReportCardSkill>) {
    onChange(value.map((skill, i) => (i === index ? { ...skill, ...next } : skill)))
  }

  function patchLabel(index: number, lang: "en" | "am" | "om", text: string) {
    const skill = value[index]
    // The key stays stable once minted — ratings entered against it must
    // survive label edits.
    onChange(
      value.map((s, i) =>
        i === index ? { ...skill, label: { ...skill.label, [lang]: text } } : s,
      ),
    )
  }

  return (
    <div className="space-y-3">
      {value.length > 0 && (
        <div className="divide-y rounded-xl border">
          {value.map((skill, index) => (
            <div key={skill.key} className="space-y-2 p-3">
              <div className="flex items-center justify-between gap-2">
                <Select
                  value={skill.group}
                  onValueChange={(v) => patch(index, { group: v as ReportCardSkill["group"] })}
                  disabled={disabled}
                >
                  <SelectTrigger
                    className="h-8 w-44 text-xs"
                    aria-label={t("reportCardPolicy.skillGroup")}
                  >
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="habits">{t("reportCardPolicy.groupHabits")}</SelectItem>
                    <SelectItem value="character">{t("reportCardPolicy.groupCharacter")}</SelectItem>
                  </SelectContent>
                </Select>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8"
                  disabled={disabled}
                  onClick={() => onChange(value.filter((_, i) => i !== index))}
                  title={t("reportCardPolicy.removeSkill")}
                  aria-label={t("reportCardPolicy.removeSkill")}
                >
                  <X className="size-4" />
                </Button>
              </div>
              <div className="grid gap-2 sm:grid-cols-3">
                {(["en", "am", "om"] as const).map((lang) => (
                  <Input
                    key={lang}
                    value={skill.label[lang]}
                    placeholder={t(`reportCardPolicy.label_${lang}`)}
                    aria-label={t(`reportCardPolicy.label_${lang}`)}
                    disabled={disabled}
                    className={lang === "am" ? "font-ethiopic" : undefined}
                    onChange={(e) => patchLabel(index, lang, e.target.value)}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {remainingSuggestions.length > 0 && !disabled && (
        <div>
          <p className="text-muted-foreground mb-1.5 text-xs">
            {t("reportCardPolicy.suggestedHint")}
          </p>
          <div className="flex flex-wrap gap-1.5">
            {remainingSuggestions.map((skill) => (
              <button
                key={skill.key}
                type="button"
                disabled={value.length >= MAX_SKILLS}
                onClick={() => onChange([...value, skill])}
                className="text-muted-foreground hover:bg-accent/50 min-h-8 rounded-full border border-dashed px-3 text-xs transition-colors disabled:opacity-50"
              >
                + {skill.label.en}
              </button>
            ))}
          </div>
        </div>
      )}

      {!disabled && (
        <Button
          variant="outline"
          size="sm"
          disabled={value.length >= MAX_SKILLS}
          onClick={() =>
            onChange([
              ...value,
              {
                key: `custom_${Date.now().toString(36)}`,
                group: "habits",
                label: { en: "", am: "", om: "" },
              },
            ])
          }
        >
          <Plus className="size-4" />
          {t("reportCardPolicy.addCustom")}
        </Button>
      )}
    </div>
  )
}
