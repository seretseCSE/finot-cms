<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'school_id', 'category', 'weight', 'room_type', 'is_active'])]
class Subject extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'language', 'mathematics', 'natural_science', 'social_science',
        'technology', 'arts_pe', 'vocational',
    ];

    /** Room types a subject may require (subset of Room::TYPES — never 'classroom'). */
    public const ROOM_TYPES = ['lab', 'library', 'ict', 'gym', 'music', 'art', 'hall'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The EXPLICIT set of grades this subject is taught in (empty = every
     * grade). Settings (load, room, category) travel with the whole set.
     *
     * @return BelongsToMany<GradeLevel, $this>
     */
    public function gradeLevels(): BelongsToMany
    {
        return $this->belongsToMany(GradeLevel::class);
    }

    /**
     * Whether this subject is taught at the given grade level (by
     * grade_levels.sort_order). Requires `gradeLevels` to be loaded —
     * eager-load it before looping.
     */
    public function appliesToGradeSort(int $sortOrder): bool
    {
        return $this->gradeLevels->isEmpty()
            || $this->gradeLevels->contains(fn (GradeLevel $g): bool => $g->sort_order === $sortOrder);
    }

    /**
     * Subjects taught in ANY of the given grade sort_orders (subjects with no
     * grade rows are open and always match).
     *
     * @param  Builder<self>  $query
     * @param  list<int>  $sortOrders
     */
    public function scopeForGradeSorts(Builder $query, array $sortOrders): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('gradeLevels')
            ->orWhereHas('gradeLevels', fn ($g) => $g->whereIn('sort_order', $sortOrders)));
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<SubjectAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(SubjectAssignment::class);
    }

    /** @return HasMany<TutorSubject, $this> */
    public function tutorSubjects(): HasMany
    {
        return $this->hasMany(TutorSubject::class);
    }
}
