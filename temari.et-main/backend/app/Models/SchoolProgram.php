<?php

namespace App\Models;

use App\Support\GradeOffering;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'branch_id', 'name', 'type', 'is_active'])]
class SchoolProgram extends Model
{
    use SoftDeletes;

    public const TYPE_REGULAR = 'regular';

    /**
     * Education program catalog (type slug → display name), aligned with how
     * Ethiopian schools run: Regular is the standard day program; Night
     * (evening), Extension (weekend, working adults), Distance, Summer (kiremt
     * in-service), Tutorial (supplementary), and Special Needs Education.
     *
     * @var array<string, string>
     */
    public const CATALOG = [
        'regular' => 'Regular',
        'night' => 'Night',
        'extension' => 'Extension',
        'distance' => 'Distance',
        'summer' => 'Summer',
        'tutorial' => 'Tutorial',
        'special_needs' => 'Special Needs Education',
    ];

    /**
     * Add a catalog program to a branch (idempotent). Reactivates a previously
     * deactivated program instead of duplicating it. A brand-new (or restored)
     * program starts offered in EVERY grade unless the caller syncs an explicit
     * grade set right after (GradeOffering::sync passes withAllGrades: false).
     */
    public static function addToBranch(Branch $branch, string $type, bool $withAllGrades = true): self
    {
        $program = self::withTrashed()->firstOrNew(
            ['branch_id' => $branch->id, 'type' => $type],
            ['school_id' => $branch->school_id],
        );
        $isNew = ! $program->exists || $program->trashed();

        $program->fill([
            'school_id' => $branch->school_id,
            'name' => self::CATALOG[$type] ?? ucfirst($type),
            'is_active' => true,
        ]);
        if ($program->trashed()) {
            $program->restore();
        }
        $program->save();

        if ($isNew && $withAllGrades) {
            $program->gradeLevels()->sync(GradeLevel::pluck('id')->all());
            GradeOffering::bust($branch);
        }

        return $program;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Grades this program is offered in at its branch — the branch's grade ×
     * program offering matrix (synced only via App\Support\GradeOffering).
     *
     * @return BelongsToMany<GradeLevel, $this>
     */
    public function gradeLevels(): BelongsToMany
    {
        return $this->belongsToMany(GradeLevel::class);
    }

    /**
     * The branch's default (regular) program — created on branch provisioning,
     * but resolved defensively for branches predating the rule.
     */
    public static function defaultFor(Branch $branch): self
    {
        return self::addToBranch($branch, self::TYPE_REGULAR);
    }
}
