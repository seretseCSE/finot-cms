<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supporting document on a transfer request (report card, fee-clearance
 * slip…) stored privately on R2. Only the storage path is persisted; access
 * always goes through signed URLs.
 */
#[Fillable(['student_transfer_request_id', 'name', 'path', 'mime_type', 'size', 'uploaded_by'])]
class TransferRequestAttachment extends Model
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
     * @return BelongsTo<StudentTransferRequest, $this>
     */
    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(StudentTransferRequest::class, 'student_transfer_request_id');
    }

    /** Short-lived signed URL — transfer documents are never public. */
    public function url(): ?string
    {
        return s3Url($this->path);
    }
}
