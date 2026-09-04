<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;
use ZipArchive;

/**
 * Everything about files sent to Temari AI. One place decides what the chat
 * accepts, how each type reaches Gemini, and how stored attachments are
 * presented back to the transcript.
 *
 * Images, PDFs and plain-text files go to the model as-is (Gemini reads
 * them natively). Office files (docx/xlsx/pptx) are NOT understood by the
 * model, so their text is extracted here — plain PHP ZipArchive + XML, no
 * extra dependencies — and sent as a named text document instead. The
 * original filename is kept on every attachment so the chat can show it.
 */
class ChatAttachments
{
    /** Extensions the composer may send (mirrored in the frontend accept list). */
    public const EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic',
        'pdf', 'txt', 'csv', 'md',
        'docx', 'xlsx', 'pptx',
    ];

    private const OFFICE_MIMES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];

    /** Character cap on text extracted from an office file (~15k tokens). */
    private const MAX_EXTRACTED_CHARS = 60000;

    /**
     * Validation rules for one uploaded attachment.
     *
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['file', 'max:8192', 'mimes:'.implode(',', self::EXTENSIONS)];
    }

    /**
     * Model for a prompt: text-only prompts use the primary (cheap) model;
     * anything carrying attachments routes to the attachment-capable model.
     * Both ids live in config/temari-ai.php.
     *
     * @param  array<int, File>  $attachments
     */
    public static function modelFor(array $attachments): string
    {
        return $attachments === []
            ? (string) config('temari-ai.model')
            : (string) config('temari-ai.attachment_model');
    }

    /** Turn an upload into the SDK attachment the model will receive. */
    public function wrap(UploadedFile $file): File
    {
        $mime = $file->getClientMimeType();
        $name = $file->getClientOriginalName();

        if (str_starts_with($mime, 'image/')) {
            return Image::fromUpload($file);
        }

        $office = self::OFFICE_MIMES[$mime] ?? null;
        if ($office === null) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $office = in_array($extension, ['docx', 'xlsx', 'pptx'], true) ? $extension : null;
        }

        if ($office !== null) {
            $text = $this->extractOfficeText($file->getRealPath(), $office);

            return Document::fromString("Contents of {$name}:\n\n{$text}", 'text/plain')->as($name);
        }

        // PDF and text types — Gemini reads these natively.
        return Base64Document::fromUpload($file)->as($name);
    }

    /**
     * Transcript metadata for a message's stored attachments — name, mime
     * and kind only, never the payload (that is served by the attachment
     * endpoint on demand).
     *
     * @param  array<int, mixed>|null  $stored
     * @return array<int, array{index: int, name: string|null, mime: string|null, kind: string}>
     */
    public function present(?array $stored): array
    {
        return collect($stored ?? [])
            ->values()
            ->map(fn (mixed $attachment, int $index): array => [
                'index' => $index,
                'name' => is_array($attachment) ? ($attachment['name'] ?? null) : null,
                'mime' => is_array($attachment) ? ($attachment['mime'] ?? null) : null,
                'kind' => is_array($attachment) && str_contains((string) ($attachment['type'] ?? ''), 'image')
                    ? 'image'
                    : 'file',
            ])
            ->all();
    }

    /**
     * Pull readable text out of a docx/xlsx/pptx without any external
     * library: they are all zip archives of XML.
     */
    public function extractOfficeText(string $path, string $kind): string
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return '(the file could not be read)';
        }

        try {
            $text = match ($kind) {
                'docx' => $this->xmlToText((string) $zip->getFromName('word/document.xml')),
                'xlsx' => $this->xlsxText($zip),
                'pptx' => $this->pptxText($zip),
                default => '',
            };
        } finally {
            $zip->close();
        }

        $text = trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? '');

        if ($text === '') {
            return '(no readable text found in the file)';
        }

        return mb_substr($text, 0, self::MAX_EXTRACTED_CHARS);
    }

    /** Strip WordprocessingML/DrawingML tags, keeping paragraph breaks. */
    private function xmlToText(string $xml): string
    {
        if ($xml === '') {
            return '';
        }

        $xml = preg_replace('/<\/(w:p|a:p)>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<(w:tab|w:br)[^>]*\/?>/', "\t", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Rows as tab-separated lines per sheet, shared strings resolved. */
    private function xlsxText(ZipArchive $zip): string
    {
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml) && $sharedXml !== '') {
            if (preg_match_all('/<si>(.*?)<\/si>/s', $sharedXml, $matches)) {
                foreach ($matches[1] as $si) {
                    $shared[] = html_entity_decode(strip_tags($si), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }

        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (! preg_match('/^xl\/worksheets\/sheet(\d+)\.xml$/', $entry, $m)) {
                continue;
            }

            $sheetXml = (string) $zip->getFromIndex($i);
            $lines = ["[Sheet {$m[1]}]"];

            if (preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rows)) {
                foreach ($rows[1] as $row) {
                    $cells = [];
                    if (preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $row, $cellMatches, PREG_SET_ORDER)) {
                        foreach ($cellMatches as $cell) {
                            $isShared = (bool) preg_match('/\bt="s"/', $cell[1]);
                            $value = preg_match('/<v>(.*?)<\/v>/s', $cell[2], $v) ? $v[1] : '';
                            $cells[] = $isShared ? ($shared[(int) $value] ?? '') : $value;
                        }
                    }
                    $line = rtrim(implode("\t", $cells));
                    if ($line !== '') {
                        $lines[] = $line;
                    }
                }
            }

            $out[] = implode("\n", $lines);
        }

        return implode("\n\n", $out);
    }

    /** Slide texts in order. */
    private function pptxText(ZipArchive $zip): string
    {
        $slides = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (preg_match('/^ppt\/slides\/slide(\d+)\.xml$/', $entry, $m)) {
                $slides[(int) $m[1]] = "[Slide {$m[1]}]\n".$this->xmlToText((string) $zip->getFromIndex($i));
            }
        }

        ksort($slides);

        return implode("\n\n", $slides);
    }
}
