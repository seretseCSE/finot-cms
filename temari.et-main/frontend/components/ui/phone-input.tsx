"use client"

import * as React from "react"

import { cn } from "@/lib/utils"
import {
  formatEthContactPhone,
  formatEthPhone,
  isEthContactPhone,
  isEthPhone,
} from "@/lib/validators"

export interface PhoneInputProps extends Omit<
  React.ComponentProps<"input">,
  "onChange" | "value" | "type"
> {
  value?: string
  onChange?: (value: string) => void
  /**
   * `mobile` (default) — Ethio Telecom / Safaricom mobiles only.
   * `contact` — also accepts geographic office landlines (school official line).
   */
  mode?: "mobile" | "contact"
}

/**
 * App-like Ethiopian phone field. Accepts every shape the shared validator does
 * (09…/07… local, +2519…/+2517… international, spaces/dashes, paste) and posts
 * the raw string — the zod `ethPhone`/`optionalEthPhone` helpers normalise it to
 * canonical `09…`/`07…` on submit. A 🇪🇹 flag anchors the field, and the value is
 * prettified on blur when it is valid.
 *
 * `mode="contact"` also accepts office landlines (e.g. `+251 11 662 98 00`) for
 * school official lines — pair with `optionalEthContactPhone`.
 *
 * Designed to drop into a shadcn `<FormControl>` via `{...field}` — it emits the
 * value string from `onChange`, which react-hook-form accepts directly.
 */
const PhoneInput = React.forwardRef<HTMLInputElement, PhoneInputProps>(
  function PhoneInput(
    {
      className,
      value = "",
      onChange,
      onBlur,
      mode = "mobile",
      "aria-invalid": ariaInvalid,
      ...props
    },
    ref
  ) {
    const contact = mode === "contact"
    const valid = contact ? isEthContactPhone(value) : isEthPhone(value)

    const handleBlur = (event: React.FocusEvent<HTMLInputElement>) => {
      // Prettify once the number is complete — the submit value is normalised by
      // zod regardless, so this is purely cosmetic.
      if (valid && onChange) {
        const pretty = contact
          ? formatEthContactPhone(value)
          : formatEthPhone(value)
        if (pretty !== value) onChange(pretty)
      }
      onBlur?.(event)
    }

    return (
      <div
        data-slot="phone-input"
        aria-invalid={ariaInvalid}
        className={cn(
          "flex h-11 w-full items-center gap-2 rounded-xl border border-input/70 bg-muted/30 px-3.5 transition-colors focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 has-[input:disabled]:pointer-events-none has-[input:disabled]:opacity-50 dark:bg-input/30",
          "aria-[invalid=true]:border-destructive aria-[invalid=true]:ring-3 aria-[invalid=true]:ring-destructive/20",
          className
        )}
      >
        <span
          className="flex shrink-0 items-center gap-1 text-muted-foreground select-none"
          aria-hidden
        >
          <span className="text-base leading-none">🇪🇹</span>
          <span className="text-sm font-medium">+251</span>
        </span>
        <span className="h-5 w-px shrink-0 bg-border" aria-hidden />
        <input
          {...props}
          ref={ref}
          // Also on the input, not just the wrapper: the wrapper copy drives the
          // focus ring, this one is what a screen reader announces.
          aria-invalid={ariaInvalid}
          type="tel"
          inputMode="tel"
          autoComplete="tel"
          value={value}
          onChange={(event) => onChange?.(event.target.value)}
          onBlur={handleBlur}
          className="h-full min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-muted-foreground md:text-sm"
        />
      </div>
    )
  }
)

export { PhoneInput }
