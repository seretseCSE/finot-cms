"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Sparkles } from "lucide-react"
import { useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
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
import type { InventoryCategory, InventoryItem } from "@/lib/types"
import { INVENTORY_UNITS } from "@/lib/types"
import { useLiveValidation } from "@/lib/use-live-validation"

const schema = z.object({
  inventory_category_id: z.string().min(1, "Category is required"),
  name: z.string().min(2, "Name is required").max(160),
  code: z.string().max(60),
  unit: z.string().min(1),
  is_asset: z.boolean(),
  reorder_level: z.string(),
  description: z.string().max(255),
  is_active: z.boolean(),
  // The "put it on the shelf" half of the guided flow (create only).
  opening_quantity: z.string(),
  units: z.string(),
  unit_cost: z.string(),
  supplier_name: z.string().max(160),
})

type FormValues = z.infer<typeof schema>

const defaults: FormValues = {
  inventory_category_id: "",
  name: "",
  code: "",
  unit: "piece",
  is_asset: false,
  reorder_level: "",
  description: "",
  is_active: true,
  opening_quantity: "",
  units: "",
  unit_cost: "",
  supplier_name: "",
}

/**
 * The guided "Add to store" sheet: what the thing IS, and — in the same
 * breath — how much of it goes on the shelf. Flipping the asset toggle
 * swaps the opening-quantity block for the tagged-units block; one save
 * writes the catalog row, the ledger receive and the unit register together.
 * Editing an existing item keeps the plain catalog fields only.
 */
export function InventoryItemSheet({
  item,
  categories,
  open,
  onOpenChange,
  onSaved,
}: {
  item: InventoryItem | null
  categories: InventoryCategory[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()
  const isEdit = !!item

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: defaults })
  useLiveValidation(form)
  const isAsset = form.watch("is_asset")
  const categoryId = form.watch("inventory_category_id")
  const unitsWanted = Number(form.watch("units") || 0)
  const openingWanted = Number(form.watch("opening_quantity") || 0)
  const needsShelf = !isEdit && (isAsset ? unitsWanted > 0 : openingWanted > 0)

  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [serials, setSerials] = useState<string[]>([])
  const [suggesting, setSuggesting] = useState(false)

  useEffect(() => {
    if (!open) return
    form.reset(
      item
        ? {
            ...defaults,
            inventory_category_id: String(item.inventory_category_id),
            name: item.name,
            code: item.code ?? "",
            unit: item.unit,
            is_asset: item.is_asset,
            reorder_level: item.reorder_level != null ? String(Number(item.reorder_level)) : "",
            description: item.description ?? "",
            is_active: item.is_active,
          }
        : defaults
    )
    setSerials([])
    setBranchId(null)
    setBranchError(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, item])

  /** ✨ Ask the server for the next free code in this category's sequence. */
  async function suggestCode() {
    if (!categoryId) return
    setSuggesting(true)
    try {
      const res = await apiFetch<{ data: { code: string } }>(
        `/inventory/items/next-code?inventory_category_id=${categoryId}`
      )
      form.setValue("code", res.data.code, { shouldValidate: true })
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSuggesting(false)
    }
  }

  async function onSubmit(values: FormValues) {
    if (needsShelf && needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    const catalog = {
      inventory_category_id: Number(values.inventory_category_id),
      name: values.name,
      code: values.code || null,
      unit: values.unit,
      is_asset: values.is_asset,
      reorder_level: values.reorder_level === "" ? null : Number(values.reorder_level),
      description: values.description || null,
      is_active: values.is_active,
    }

    try {
      if (isEdit) {
        await apiFetch(`/inventory/items/${item!.id}`, { method: "PUT", body: catalog })
        toast.success(t("items.saved"))
      } else {
        await apiFetch("/inventory/items/quick-add", {
          method: "POST",
          body: {
            ...catalog,
            ...(branchId != null ? { branch_id: branchId } : {}),
            opening_quantity: values.is_asset ? null : Number(values.opening_quantity || 0),
            units: values.is_asset ? Number(values.units || 0) : null,
            serial_numbers: values.is_asset ? serials.slice(0, unitsWanted).map((s) => s || null) : null,
            condition: values.is_asset ? "good" : undefined,
            unit_cost: values.unit_cost === "" ? null : Number(values.unit_cost),
            supplier_name: values.supplier_name || null,
          },
        })
        toast.success(t("items.quickAdded"))
      }
      onSaved()
      onOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error(tc("errors.generic"))
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("items.editTitle") : t("items.addTitle")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-4">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("items.name")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("items.namePlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="inventory_category_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("items.category")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("items.category")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {categories
                            .filter((c) => c.is_active)
                            .map((category) => (
                              <SelectItem key={category.id} value={String(category.id)}>
                                {category.name}
                              </SelectItem>
                            ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="unit"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("items.unit")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {INVENTORY_UNITS.map((unit) => (
                            <SelectItem key={unit} value={unit}>
                              {t(`units.${unit}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="code"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("items.code")}</FormLabel>
                      <div className="flex gap-1.5">
                        <FormControl>
                          <Input placeholder={t("items.codePlaceholder")} {...field} />
                        </FormControl>
                        <Button
                          type="button"
                          variant="outline"
                          size="icon"
                          className="size-10 shrink-0"
                          loading={suggesting}
                          disabled={!categoryId}
                          onClick={suggestCode}
                          title={t("items.generateCode")}
                          aria-label={t("items.generateCode")}
                        >
                          <Sparkles className="size-4" />
                        </Button>
                      </div>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="reorder_level"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("items.reorderLevel")}</FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          inputMode="decimal"
                          min={0}
                          className="no-spinner"
                          placeholder="—"
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
              <p className="text-xs text-muted-foreground">{t("items.reorderHelp")}</p>

              <FormField
                control={form.control}
                name="description"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("items.descriptionLabel")}</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="is_asset"
                render={({ field }) => (
                  <FormItem className="flex items-center justify-between gap-3 rounded-xl border p-3">
                    <div className="space-y-0.5">
                      <FormLabel>{t("items.asset")}</FormLabel>
                      <p className="text-xs text-muted-foreground">{t("items.assetToggleHelp")}</p>
                    </div>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} disabled={isEdit && item?.is_asset} />
                    </FormControl>
                  </FormItem>
                )}
              />

              {/* ── Put it on the shelf (create only) ── */}
              {!isEdit && (
                <div className="space-y-3 rounded-xl border bg-muted/40 p-3">
                  <p className="text-sm font-medium">
                    {isAsset ? t("items.unitsSectionTitle") : t("items.openingSectionTitle")}
                  </p>

                  {needsShelf && needsBranch && (
                    <BranchField
                      value={branchId}
                      onChange={(id) => {
                        setBranchId(id)
                        setBranchError(null)
                      }}
                      error={branchError}
                    />
                  )}

                  {isAsset ? (
                    <>
                      <div className="grid grid-cols-2 gap-3">
                        <FormField
                          control={form.control}
                          name="units"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>{t("assets.quantityLabel")}</FormLabel>
                              <FormControl>
                                <Input
                                  type="number"
                                  inputMode="numeric"
                                  min={0}
                                  max={100}
                                  className="no-spinner"
                                  placeholder="0"
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                        <FormField
                          control={form.control}
                          name="unit_cost"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>{t("assets.unitCost")}</FormLabel>
                              <FormControl>
                                <Input
                                  type="number"
                                  inputMode="decimal"
                                  min={0}
                                  className="no-spinner"
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </div>
                      <p className="text-xs text-muted-foreground">{t("items.unitsSectionHelp")}</p>
                      {unitsWanted > 0 && unitsWanted <= 20 && (
                        <div className="space-y-1.5">
                          <Label>{t("assets.serialsLabel")}</Label>
                          {Array.from({ length: unitsWanted }).map((_, i) => (
                            <Input
                              key={i}
                              placeholder={t("assets.serialPlaceholder").replace("{n}", String(i + 1))}
                              value={serials[i] ?? ""}
                              onChange={(e) =>
                                setSerials((s) => {
                                  const next = [...s]
                                  next[i] = e.target.value
                                  return next
                                })
                              }
                            />
                          ))}
                        </div>
                      )}
                    </>
                  ) : (
                    <>
                      <div className="grid grid-cols-2 gap-3">
                        <FormField
                          control={form.control}
                          name="opening_quantity"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>{t("items.openingQuantity")}</FormLabel>
                              <FormControl>
                                <Input
                                  type="number"
                                  inputMode="decimal"
                                  min={0}
                                  className="no-spinner"
                                  placeholder="0"
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                        <FormField
                          control={form.control}
                          name="unit_cost"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>{t("movement.unitCostLabel")}</FormLabel>
                              <FormControl>
                                <Input
                                  type="number"
                                  inputMode="decimal"
                                  min={0}
                                  className="no-spinner"
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </div>
                      <FormField
                        control={form.control}
                        name="supplier_name"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel>{t("movement.supplierLabel")}</FormLabel>
                            <FormControl>
                              <Input placeholder={t("movement.supplierPlaceholder")} {...field} />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                      <p className="text-xs text-muted-foreground">{t("items.openingSectionHelp")}</p>
                    </>
                  )}
                </div>
              )}

              {isEdit && (
                <FormField
                  control={form.control}
                  name="is_active"
                  render={({ field }) => (
                    <FormItem className="flex items-center justify-between gap-3 rounded-xl border p-3">
                      <FormLabel>{t("items.isActiveLabel")}</FormLabel>
                      <FormControl>
                        <Switch checked={field.value} onCheckedChange={field.onChange} />
                      </FormControl>
                    </FormItem>
                  )}
                />
              )}
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button
                type="button"
                variant="outline"
                className="h-11 flex-1"
                onClick={() => onOpenChange(false)}
                disabled={form.formState.isSubmitting}
              >
                {tc("actions.cancel")}
              </Button>
              <Button type="submit" className="h-11 flex-1" loading={form.formState.isSubmitting}>
                {isEdit ? tc("actions.save") : t("items.addToStore")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
