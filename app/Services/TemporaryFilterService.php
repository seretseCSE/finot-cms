<?php

namespace App\Services;

use App\Models\TemporaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TemporaryFilterService
{
    /**
     * Save a temporary filter for the current user.
     */
    public function saveFilter(string $name, string $resourceType, array $criteria, ?\DateTime $expiresAt = null): TemporaryFilter
    {
        return TemporaryFilter::create([
            'name' => $name,
            'resource_type' => $resourceType,
            'filter_criteria' => $criteria,
            'user_id' => Auth::id(),
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
    }

    /**
     * Apply a temporary filter to a query builder.
     */
    public function applyFilter(TemporaryFilter $filter, Builder $query): Builder
    {
        $criteria = $filter->filter_criteria ?? [];

        foreach ($criteria as $field => $value) {
            if (blank($value)) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } elseif (str_contains($field, '.')) {
                // Handle relationship filters
                $this->applyRelationshipFilter($query, $field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Get active temporary filters for a resource type.
     */
    public function getActiveFilters(string $resourceType): array
    {
        return TemporaryFilter::active()
            ->forResource($resourceType)
            ->forUser(Auth::id())
            ->get()
            ->toArray();
    }

    /**
     * Apply a relationship filter.
     */
    protected function applyRelationshipFilter(Builder $query, string $field, mixed $value): void
    {
        [$relation, $column] = explode('.', $field, 2);

        $query->whereHas($relation, function ($q) use ($column, $value) {
            if (is_array($value)) {
                $q->whereIn($column, $value);
            } else {
                $q->where($column, $value);
            }
        });
    }

    /**
     * Clean up expired temporary filters.
     */
    public function cleanupExpired(): int
    {
        return TemporaryFilter::query()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);
    }
}
