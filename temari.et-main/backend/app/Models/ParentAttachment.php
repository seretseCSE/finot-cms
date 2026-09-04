<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guardian document (ID, custody letter…) stored privately on R2. Only the
 * storage path is persisted; access always goes through signed URLs.
 */
#[Fillable(['parent_id', 'name', 'category', 'path', 'mime_type', 'size', 'school_id', 'branch_id', 'uploaded_by'])]
class ParentAttachment extends Model
{
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
     * @return BelongsTo<ParentProfile, $this>
     */
    public function parentProfile(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class, 'parent_id');
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

    /** Short-lived signed URL — guardian documents are never public. */
    public function url(): ?string
    {
        return s3Url($this->path);
    }
}
