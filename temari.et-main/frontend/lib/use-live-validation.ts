"use client"

import { useEffect } from "react"
import type { FieldValues, Path, UseFormReturn } from "react-hook-form"

/**
 * Makes visible field errors self-healing: as soon as the user edits a field
 * that currently shows an error, it re-validates (schema errors clear the
 * moment the value is valid) and server-set errors (`type: "server"`, set from
 * an ApiError) are dropped outright — the server re-checks on the next submit.
 *
 * Pair with `form.setError(field, { type: "server", message })` in catch
 * blocks; without the type the error still clears once the schema passes.
 */
export function useLiveValidation<T extends FieldValues>(form: UseFormReturn<T>) {
  useEffect(() => {
    const subscription = form.watch((_, { name }) => {
      if (!name) return
      const field = name as Path<T>
      const { error } = form.getFieldState(field)
      if (!error) return
      if (error.type === "server") form.clearErrors(field)
      else void form.trigger(field)
    })
    return () => subscription.unsubscribe()
  }, [form])
}
