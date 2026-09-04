<?php

namespace App\Support;

use App\Enums\TermStatus;
use App\Models\Term;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * THE single write-gate for time-scoped academic data (ADR-011). Every mutation
 * of a record anchored to a term (attendance, assessments, marks, timetable)
 * must pass through here, so "previous term is read-only once closed" is a
 * structural guarantee instead of a per-endpoint convention.
 */
final class TermGate
{
    /**
     * Throws 422 when the term is closed. A null term (record not yet anchored
     * to any term) is allowed through — anchoring is best-effort during v1.
     */
    public static function assertWritable(?Term $term): void
    {
        if ($term !== null && $term->status === TermStatus::Closed) {
            throw new HttpException(422, "\"{$term->name}\" is closed — its records are read-only.");
        }
    }
}
