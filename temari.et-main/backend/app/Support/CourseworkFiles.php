<?php

namespace App\Support;

/**
 * The one list of file types classwork accepts — a teacher's reference files
 * on an assignment and a student's turn-in are the same kind of thing, so
 * they share it. Mirrored in the frontend accept string
 * (`frontend/lib/files.ts` COURSEWORK_ACCEPT); the two must never drift, or a
 * picker offers a type the endpoint refuses.
 *
 * Deliberately absent: anything the browser executes when a signed R2 link is
 * opened inline (svg, html, js) and anything runnable (exe, apk, sh).
 */
class CourseworkFiles
{
    /**
     * NOTE ON `txt`: Laravel's `mimes:` rule matches the extension GUESSED
     * from the file's real content (finfo), not the one the user typed. A
     * well-formed .csv guesses back as `csv` on the libmagic builds we tested,
     * but a plain-text data file that libmagic can't fingerprint falls back to
     * `text/plain` → `txt` — and libmagic differs between macOS dev machines
     * and the Linux VPS. `txt` on the list is what keeps that difference from
     * becoming "works locally, rejects a parent's export in production".
     *
     * @var list<string>
     */
    public const EXTENSIONS = [
        // documents
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'md',
        // slides
        'ppt', 'pptx', 'odp',
        // spreadsheets and data
        'xls', 'xlsx', 'ods', 'csv',
        // pictures (heic = the iPhone default)
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic',
        // audio — amr/m4a are what cheap Android recorders produce
        'mp3', 'm4a', 'aac', 'ogg', 'amr', 'wav',
        // video
        'mp4', 'webm',
        // bundles
        'zip',
    ];

    /** Per-file cap in kilobytes — one 3G-friendly limit for the whole lane. */
    public const MAX_KB = 20480;

    /**
     * Validation rules for one uploaded coursework file.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::EXTENSIONS)];
    }
}
