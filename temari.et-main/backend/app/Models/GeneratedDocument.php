<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One backend-generated official PDF: pre-rendered by GenerateDocumentJob,
 * cached on R2 by (type, subject, params, version_hash), publicly verifiable
 * by `public_token` (the QR target). Revoking a document kills its QR without
 * deleting history — reissue creates a fresh row.
 */
#[Fillable([
    'school_id', 'branch_id', 'type', 'subject_type', 'subject_id', 'params',
    'version_hash', 'status', 'disk_path', 'size_bytes', 'error',
    'public_token', 'requested_by', 'revoked_at',
])]
class GeneratedDocument extends Model
{
    use SoftDeletes;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->public_token ??= (string) Str::uuid();
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->disk_path !== null;
    }

    /**
     * The newest usable PDF for a subject — what the PUBLIC token pages hand
     * out so a family prints the official document instead of the web page.
     * Null when the document was never pre-warmed (or was revoked), in which
     * case the page falls back to its on-screen article.
     */
    public static function latestReadyFor(string $type, Model $subject): ?self
    {
        return self::query()
            ->where('type', $type)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('status', self::STATUS_READY)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }

    /**
     * Download + inline URLs for a public page, always both keys so the
     * frontend can render its PDF buttons from one shape.
     *
     * @return array{download_url: string|null, view_url: string|null}
     */
    public static function publicUrlsFor(string $type, Model $subject): array
    {
        $document = self::latestReadyFor($type, $subject);

        return [
            'download_url' => $document?->downloadUrl(),
            'view_url' => $document?->viewUrl(),
        ];
    }

    /** Short-lived signed URL for the stored PDF (null until ready). */
    public function downloadUrl(): ?string
    {
        return $this->isReady() ? s3Url($this->disk_path, download: true) : null;
    }

    /**
     * Signed URL that DISPLAYS the PDF in the browser (inline disposition)
     * instead of forcing a download — what the "Print" flow opens so the
     * tab shows the document rather than sitting blank while a file lands
     * in the downloads folder.
     */
    public function viewUrl(): ?string
    {
        return $this->isReady() ? s3Url($this->disk_path) : null;
    }
}
