<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student document (birth certificate, ID, transfer letter…) stored
 * privately on R2. Only the storage path is persisted; access always goes
 * through signed URLs.
 */
#[Fillable(['student_id', 'name', 'category', 'path', 'mime_type', 'size', 'school_id', 'branch_id', 'uploaded_by'])]
class StudentAttachment extends Model
{
    // Retention (ADR-017): soft-deletes hide a document from the live file;
    // rows referenced by a handover snapshot are never force-deleted, so a
    // former school's frozen archive keeps opening its copy.
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Provenance: the branch that collected the document.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Short-lived signed URL — student documents are never public. */
    public function url(): ?string
    {
        return s3Url($this->path);
    }
}
