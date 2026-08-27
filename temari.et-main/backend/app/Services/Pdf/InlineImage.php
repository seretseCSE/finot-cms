<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Inline an R2 object as a base64 data URI for the PDF pipeline. The
 * renderer's contract is SELF-CONTAINED HTML (PdfRenderer): the remote
 * browser never fetches signed URLs, so every image a template shows —
 * student photos, school logos — must travel inside the HTML, exactly like
 * the QR does. Bonus: data URIs are stable per file content, so the PDF
 * cache stops churning with signed-URL rotation.
 */
class InlineImage
{
    public static function fromStorage(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            $disk = Storage::disk(config('filesystems.default'));

            if (! $disk->exists($path)) {
                return null;
            }

            $mime = $disk->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
        } catch (Throwable) {
            // A missing/unreadable image must never sink the whole document.
            return null;
        }
    }
}
