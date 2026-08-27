<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tutor verification document, private on R2, served via signed URLs.
 * Teacher imports carry provenance in source_employee_attachment_id.
 */
#[Fillable([
    'tutor_profile_id', 'name', 'category', 'path', 'mime_type', 'size',
    'source_employee_attachment_id',
])]
class TutorAttachment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /**
     * @return BelongsTo<TutorProfile, $this>
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    public function url(): ?string
    {
        return s3Url($this->path);
    }
}
