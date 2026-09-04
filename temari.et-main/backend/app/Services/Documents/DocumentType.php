<?php

namespace App\Services\Documents;

use App\Models\GeneratedDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One official-document type in the PDF pipeline: how to resolve and
 * authorize its subject, assemble the (hashable) view payload, and what the
 * public QR verification page may reveal. Register implementations in
 * DocumentService::TYPES.
 */
abstract class DocumentType
{
    /** The blade view under resources/views/documents/. */
    abstract public function view(): string;

    /** Load the subject row (unscoped — authorize() gates access). */
    abstract public function resolveSubject(?int $subjectId): ?Model;

    /**
     * Whether the user may generate/fetch this document for this subject.
     * Row-anchored (hasPermissionForScope) — never trust context headers.
     *
     * @param  array<string, mixed>  $params
     */
    abstract public function authorize(User $user, ?Model $subject, array $params): bool;

    /**
     * Tenancy anchor stamped on the generated_documents row.
     *
     * @param  array<string, mixed>  $params
     * @return array{school_id: int|null, branch_id: int|null}
     */
    abstract public function anchor(?Model $subject, array $params): array;

    /**
     * Everything the view needs. Hashed (minus volatile keys) for the PDF
     * cache: unchanged data re-serves the stored PDF instead of re-rendering.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    abstract public function payload(?Model $subject, array $params): array;

    /**
     * The minimal summary the public QR verification page shows — proof of
     * authenticity only (who/what/when/valid), never marks or amounts.
     *
     * @return array<string, mixed>
     */
    abstract public function verifySummary(GeneratedDocument $document): array;

    /** Validation rules for `params`. */
    public function rules(): array
    {
        return [];
    }

    public function landscape(): bool
    {
        return false;
    }

    /** Whether the PDF itself may be fetched by public token (receipts/letters). */
    public function publiclyDownloadable(): bool
    {
        return false;
    }

    /** Post-generation hook (notifications). Must never throw. */
    public function onReady(GeneratedDocument $document): void
    {
    }

    /** Payload keys excluded from the content hash (timestamps, QR URIs). */
    public function volatileKeys(): array
    {
        return ['generated_at', 'qr'];
    }

    /**
     * Mixed into the content hash so a template redesign invalidates every
     * cached PDF. Bump whenever the blade views change visibly.
     */
    public function templateVersion(): int
    {
        return 3;
    }

    /**
     * The URL the PDF's QR encodes. Types whose frontend article already has
     * a public page (receipt, letters) point at THAT page so paper and web
     * QR codes resolve identically; everything else uses the verify lane.
     */
    public function qrTarget(GeneratedDocument $document): string
    {
        return rtrim((string) config('sms.frontend_url'), '/').'/verify/'.$document->public_token;
    }
}
