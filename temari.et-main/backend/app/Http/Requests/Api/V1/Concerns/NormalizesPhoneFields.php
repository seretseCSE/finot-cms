<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Support\PhoneNumber;

/**
 * Canonicalises declared phone fields to the local form BEFORE validation
 * runs, so `unique` checks compare like-for-like and every value reaching the
 * controller/action is already normalised. Un-normalisable input is left as
 * typed for the matching rule (`EthiopianPhone` / `EthiopianContactPhone`) to
 * reject with a clear message.
 *
 * - `phoneFields()` → mobile only (`09…` / `07…`)
 * - `contactPhoneFields()` → mobile OR office landline (`011…`, etc.)
 *
 * A request opts in by listing fields and calling `normalizeDeclaredPhones()`
 * from its `prepareForValidation()` (or simply relying on the default
 * `prepareForValidation()` below when it needs no other preparation).
 */
trait NormalizesPhoneFields
{
    /**
     * @return list<string>
     */
    protected function phoneFields(): array
    {
        return [];
    }

    /**
     * School (and similar) office lines that may be geographic landlines.
     *
     * @return list<string>
     */
    protected function contactPhoneFields(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeDeclaredPhones();
    }

    protected function normalizeDeclaredPhones(): void
    {
        $updates = [];

        foreach ($this->phoneFields() as $field) {
            $value = $this->input($field);

            if (is_string($value) && trim($value) !== '') {
                $updates[$field] = PhoneNumber::normalize($value) ?? $value;
            }
        }

        foreach ($this->contactPhoneFields() as $field) {
            $value = $this->input($field);

            if (is_string($value) && trim($value) !== '') {
                $updates[$field] = PhoneNumber::normalizeContact($value) ?? $value;
            }
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    /**
     * Normalise phone keys inside each row of an array field (e.g. the
     * `guardians` rows on a registration payload).
     *
     * @param  list<string>  $subKeys
     */
    protected function normalizeNestedPhones(string $arrayKey, array $subKeys): void
    {
        $rows = $this->input($arrayKey);

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($subKeys as $key) {
                $value = $row[$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $rows[$i][$key] = PhoneNumber::normalize($value) ?? $value;
                }
            }
        }

        $this->merge([$arrayKey => $rows]);
    }
}
