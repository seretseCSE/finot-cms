<?php

namespace App\Http\Controllers\Concerns;

use App\Support\SearchTerm;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared building blocks for server-driven list endpoints: whitelisted sorting,
 * clamped pagination, multi-select (CSV) filters, inclusive date ranges and
 * tri-state boolean filters.
 *
 * Every list controller (users, schools, branches, employees, …) should lean on
 * these so the query contract stays identical across the platform. When you add
 * a new list table, `use HandlesListQueries` and follow the users controller.
 */
trait HandlesListQueries
{
    /**
     * Clamp the requested page size to a sane range.
     */
    protected function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        return min(max((int) $request->input('per_page', $default), 1), $max);
    }

    /**
     * Apply a whitelisted `sort` / `dir` ordering. Unknown fields fall back to
     * $default so a hostile or stale client can never sort by an arbitrary column.
     *
     * @param  Builder<*>  $query
     * @param  list<string>  $allowed
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default, string $defaultDir = 'desc'): void
    {
        $field = in_array($request->input('sort'), $allowed, true)
            ? (string) $request->input('sort')
            : $default;

        $dir = $request->input('dir') === 'asc' ? 'asc' : ($request->input('dir') === 'desc' ? 'desc' : $defaultDir);

        $query->orderBy($field, $dir);
    }

    /**
     * Apply the request's free-text search WORD BY WORD (see
     * `App\Support\SearchTerm` for why: split Ethiopian name columns mean a
     * full name matches no single column).
     *
     * $match gets one needle at a time and ORs it across everything it may
     * hit. Use `$this->needle($n)` for ILIKE patterns:
     *
     *     $this->applySearch($query, $request, fn ($q, $n) => $q
     *         ->where('search_text', 'ilike', $this->needle($n))
     *         ->orWhere('public_id', PublicId::normalize($n)));
     *
     * @param  Builder<*>  $query
     * @param  Closure(mixed, string): void  $match
     */
    protected function applySearch(Builder $query, Request $request, Closure $match, string $key = 'search'): void
    {
        SearchTerm::apply($query, $request->string($key)->trim()->value(), $match);
    }

    /** A `%…%` ILIKE needle with the user's own wildcards neutralised. */
    protected function needle(string $value): string
    {
        return SearchTerm::contains($value);
    }

    /**
     * Constrain a column to an inclusive `[<fromKey>, <toKey>]` date window.
     * Either bound may be omitted.
     *
     * @param  Builder<*>  $query
     */
    protected function applyDateRange(Builder $query, Request $request, string $column, string $fromKey, string $toKey): void
    {
        if ($from = $request->date($fromKey)) {
            $query->where($column, '>=', $from->startOfDay());
        }
        if ($to = $request->date($toKey)) {
            $query->where($column, '<=', $to->endOfDay());
        }
    }

    /**
     * Apply a tri-state boolean filter driven by a `"true"` / `"false"`
     * multi-select. Selecting both (or neither) is a no-op.
     *
     * @param  Builder<*>  $query
     */
    protected function applyBooleanFilter(Builder $query, Request $request, string $key, string $column): void
    {
        $values = $this->csvValues($request, $key);
        $wantsTrue = in_array('true', $values, true);
        $wantsFalse = in_array('false', $values, true);

        if ($wantsTrue xor $wantsFalse) {
            $query->where($column, $wantsTrue);
        }
    }

    /**
     * Toggle soft-deleted rows into the result set via a `trashed=only|with`
     * param. Callers must gate this on the appropriate delete permission before
     * calling — this only translates the param into a scope.
     *
     * @param  Builder<*>  $query
     */
    protected function applyTrashedFilter(Builder $query, Request $request): void
    {
        match ($request->string('trashed')->trim()->value()) {
            'only' => $query->onlyTrashed(),
            'with' => $query->withTrashed(),
            default => null,
        };
    }

    /**
     * Parse a comma-separated multi-select filter param into distinct values.
     *
     * @return list<string>
     */
    protected function csvValues(Request $request, string $key): array
    {
        $raw = $request->string($key)->trim()->value();
        if ($raw === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
    }

    /**
     * @return list<int>
     */
    protected function csvIds(Request $request, string $key): array
    {
        return array_values(array_map(
            'intval',
            array_filter($this->csvValues($request, $key), fn (string $v) => ctype_digit($v)),
        ));
    }
}
