"use client"

import { useFormContext, useWatch, type FieldValues, type Path, type PathValue } from "react-hook-form"

import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  REGIONS,
  getCitiesForRegion,
  getSubCitiesForCity,
  getWoredasForSubCity,
} from "@/lib/data/ethiopia-locations"
import { useTranslation } from "@/lib/i18n"

/**
 * Cascading Ethiopian address fieldset (region → city → sub-city → woreda →
 * house no). Expects the parent form to declare string fields named `state`,
 * `city`, `sub_city`, `woreda`, `house_no` — or `<prefix>state` etc. when a
 * `prefix` is given (e.g. "birth_" for a birthplace block, which also drops
 * the house-no field). Levels without seeded options fall back to a free-text
 * input, so rural addresses are never blocked.
 */
export function AddressFields<T extends FieldValues>({
  prefix = "",
  withHouseNo = true,
}: {
  prefix?: string
  withHouseNo?: boolean
} = {}) {
  const { t } = useTranslation("schools")
  const form = useFormContext<T>()

  const name = (key: string) => `${prefix}${key}` as Path<T>
  const set = (key: string, value: string) =>
    form.setValue(name(key), value as PathValue<T, Path<T>>)

  const region = useWatch({ control: form.control, name: name("state") }) as string | undefined
  const city = useWatch({ control: form.control, name: name("city") }) as string | undefined
  const subCity = useWatch({ control: form.control, name: name("sub_city") }) as string | undefined

  const cities = getCitiesForRegion(region ?? "")
  const subCities = getSubCitiesForCity(city ?? "")
  const woredas = getWoredasForSubCity(subCity ?? "")

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <FormField
        control={form.control}
        name={name("state")}
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t("branches.state")}</FormLabel>
            <Select
              value={field.value ?? ""}
              onValueChange={(val) => {
                field.onChange(val)
                set("city", "")
                set("sub_city", "")
                set("woreda", "")
              }}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t("address.selectRegion")} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {REGIONS.map((r) => (
                  <SelectItem key={r} value={r}>
                    {r}
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
        name={name("city")}
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t("branches.city")}</FormLabel>
            {cities.length > 0 ? (
              <Select
                value={field.value ?? ""}
                onValueChange={(val) => {
                  field.onChange(val)
                  set("sub_city", "")
                  set("woreda", "")
                }}
                disabled={!region}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue
                      placeholder={region ? t("address.selectCity") : t("address.regionFirst")}
                    />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {cities.map((c) => (
                    <SelectItem key={c} value={c}>
                      {c}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ) : (
              <FormControl>
                <Input {...field} value={field.value ?? ""} placeholder={t("address.cityPlaceholder")} />
              </FormControl>
            )}
            <FormMessage />
          </FormItem>
        )}
      />

      <FormField
        control={form.control}
        name={name("sub_city")}
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t("branches.subCity")}</FormLabel>
            {subCities.length > 0 ? (
              <Select
                value={field.value ?? ""}
                onValueChange={(val) => {
                  field.onChange(val)
                  set("woreda", "")
                }}
                disabled={!city}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("branches.subCity")} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {subCities.map((sc) => (
                    <SelectItem key={sc} value={sc}>
                      {sc}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ) : (
              <FormControl>
                <Input {...field} value={field.value ?? ""} placeholder={t("branches.subCity")} />
              </FormControl>
            )}
            <FormMessage />
          </FormItem>
        )}
      />

      <FormField
        control={form.control}
        name={name("woreda")}
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t("branches.woreda")}</FormLabel>
            {woredas.length > 0 ? (
              <Select value={field.value ?? ""} onValueChange={field.onChange} disabled={!subCity}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("branches.woreda")} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {woredas.map((w) => (
                    <SelectItem key={w} value={w}>
                      {w}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ) : (
              <FormControl>
                <Input {...field} value={field.value ?? ""} placeholder={t("branches.woreda")} />
              </FormControl>
            )}
            <FormMessage />
          </FormItem>
        )}
      />

      {withHouseNo ? (
        <FormField
          control={form.control}
          name={name("house_no")}
          render={({ field }) => (
            <FormItem className="sm:col-span-2">
              <FormLabel>{t("branches.houseNo")}</FormLabel>
              <FormControl>
                <Input {...field} value={field.value ?? ""} placeholder={t("branches.houseNoPlaceholder")} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      ) : null}
    </div>
  )
}
