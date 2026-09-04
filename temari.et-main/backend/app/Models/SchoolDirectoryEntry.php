<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One Ethiopian school in the platform-wide directory — on Temari (school_id
 * set, verified) or off-platform (seeded, or added inline by a registrar as
 * unverified with created_by_school_id provenance). Used for "previous school"
 * on enrollments; platform staff verify or merge contributed rows.
 */
#[Fillable(['name', 'region', 'zone', 'city', 'school_id', 'is_verified', 'created_by_school_id'])]
class SchoolDirectoryEntry extends Model
{
    use SoftDeletes;

    protected $table = 'school_directory';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function createdBySchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'created_by_school_id');
    }
}
