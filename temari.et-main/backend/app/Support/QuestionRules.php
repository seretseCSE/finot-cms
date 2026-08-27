<?php

namespace App\Support;

use App\Enums\QuestionType;
use Illuminate\Validation\ValidationException;

/**
 * Type-shaped validation for question bodies and answer keys (ADR-016).
 * Shared by every place questions are authored (school bank, national bank
 * studio, bulk import) so a malformed question can never enter the pool.
 */
class QuestionRules
{
    /** Tags allowed in a question stem (WYSIWYG output). */
    private const STEM_TAGS = '<p><br><b><strong><i><em><u><s><strike><del><sub><sup>'
        .'<h1><h2><h3><h4><blockquote><pre><code><hr><ul><ol><li><img><a><span><div>';

    /** Attachment kinds a question may carry alongside its stem. */
    public const ATTACHMENT_KINDS = ['file', 'link', 'youtube'];

    /** Baseline request rules, independent of type. */
    public static function base(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', array_column(QuestionType::cases(), 'value'))],
            'body' => ['required', 'array'],
            'body.stem' => ['required', 'string', 'max:20000'],
            'answer_key' => ['nullable', 'array'],
            'points' => ['sometimes', 'numeric', 'min:0.25', 'max:100'],
            'difficulty' => ['nullable', 'string', 'in:easy,medium,hard'],
            'topic' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:60'],
            'source' => ['nullable', 'string', 'max:120'],
            'explanation' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'string', 'in:draft,published,retired'],
        ];
    }

    /**
     * Server-side defence for the rich stem: strip everything outside the
     * WYSIWYG allowlist and neutralise script-bearing attributes. The
     * frontend sanitises again on render — belt and braces, takers included.
     */
    public static function sanitizeStem(string $html): string
    {
        $html = strip_tags($html, self::STEM_TAGS);
        // Event handlers (onclick=…) and javascript:/data: URLs never survive.
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*(["\']?)\s*(javascript|vbscript|data:text)[^"\'>\s]*\2/i', '', $html) ?? $html;
        // <div> exists solely to carry a video marker: keep a validated
        // `provider:id`, drop every other attribute (and invalid markers).
        $html = preg_replace_callback('/<div\b[^>]*>/i', function (array $m): string {
            if (preg_match('/data-video\s*=\s*"((?:youtube|vimeo):[A-Za-z0-9_-]{1,32})"/i', $m[0], $marker)) {
                return '<div data-video="'.$marker[1].'">';
            }

            return '<div>';
        }, $html) ?? $html;

        // A math marker (<span data-math="…latex…">) keeps ONLY its LaTeX
        // source + display flag — the renderer feeds it to KaTeX as text, so
        // no other attribute may ride along. Plain spans lose nothing here
        // (the frontend sanitizer strips their attributes on render).
        $html = preg_replace_callback('/<span\b[^>]*data-math[^>]*>/i', function (array $m): string {
            if (! preg_match('/data-math\s*=\s*"([^"]{1,2000})"/i', $m[0], $latex)) {
                return '<span>';
            }
            $display = preg_match('/data-display\s*=\s*"(?:block|true)"/i', $m[0]) === 1;

            return '<span data-math="'.$latex[1].'"'.($display ? ' data-display="block"' : '').'>';
        }, $html) ?? $html;

        return trim($html);
    }

    /**
     * Sanitize a short rich text (an MCQ option, a matching item). Plain
     * strings pass through untouched — a legacy "x < 5" must never be
     * mangled by the HTML pipeline — only tag-bearing values are treated
     * as WYSIWYG output.
     */
    public static function sanitizeInline(string $text): string
    {
        if (preg_match('/<[a-z][^>]*>/i', $text) !== 1) {
            return $text;
        }

        return self::normalizeStemMedia(self::sanitizeStem($text));
    }

    /**
     * Shape the body for storage: sanitized stem, attachments reduced to
     * their persistent keys (signed `url`s are minted at read time for file
     * uploads, never stored).
     */
    public static function normalizeBody(array $body): array
    {
        $body['stem'] = self::normalizeStemMedia(self::sanitizeStem((string) ($body['stem'] ?? '')));

        // Answer choices and matching items may carry rich text too (math,
        // images) — same allowlist, same stored-media shape as the stem.
        foreach (['options', 'left', 'right'] as $listKey) {
            if (isset($body[$listKey]) && is_array($body[$listKey])) {
                $body[$listKey] = array_values(array_map(function ($item): mixed {
                    if (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                        $item['text'] = self::sanitizeInline($item['text']);
                    }

                    return $item;
                }, $body[$listKey]));
            }
        }

        if (isset($body['attachments']) && is_array($body['attachments'])) {
            $body['attachments'] = array_values(array_map(function ($file): array {
                $keep = ['kind', 'name', 'mime_type', 'size'];
                $keep[] = ($file['kind'] ?? null) === 'file' ? 'path' : 'url';

                return array_intersect_key(is_array($file) ? $file : [], array_flip($keep));
            }, $body['attachments']));

            if ($body['attachments'] === []) {
                unset($body['attachments']);
            }
        }

        return $body;
    }

    /**
     * Uploaded stem images arrive as `<img src="signed-url" data-path="…">`.
     * Signed URLs expire, so only the R2 path is persisted — fresh URLs are
     * minted per read by hydrateStemMedia(). External http(s) images keep
     * their src; anything else is dropped.
     */
    public static function normalizeStemMedia(string $html): string
    {
        return preg_replace_callback('/<img\b[^>]*>/i', function (array $match): string {
            if (preg_match('/data-path\s*=\s*"([^"]+)"/i', $match[0], $path)) {
                return '<img data-path="'.htmlspecialchars(html_entity_decode($path[1]), ENT_QUOTES).'">';
            }
            if (preg_match('/src\s*=\s*"(https?:[^"]+)"/i', $match[0], $src)) {
                return '<img src="'.htmlspecialchars(html_entity_decode($src[1]), ENT_QUOTES).'">';
            }

            return '';
        }, $html) ?? $html;
    }

    /**
     * Hydrate every rich text in a stored body for clients: the stem plus
     * option/matching texts get their `<img data-path>` markers re-armed.
     */
    public static function hydrateBodyMedia(array $body): array
    {
        if (isset($body['stem']) && is_string($body['stem'])) {
            $body['stem'] = self::hydrateStemMedia($body['stem']);
        }

        foreach (['options', 'left', 'right'] as $listKey) {
            if (isset($body[$listKey]) && is_array($body[$listKey])) {
                $body[$listKey] = array_values(array_map(function ($item): mixed {
                    if (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                        $item['text'] = self::hydrateStemMedia($item['text']);
                    }

                    return $item;
                }, $body[$listKey]));
            }
        }

        return $body;
    }

    /** Re-arm stored `<img data-path>` markers with fresh signed URLs. */
    public static function hydrateStemMedia(string $html): string
    {
        return preg_replace_callback('/<img data-path="([^"]+)">/i', function (array $match): string {
            $path = html_entity_decode($match[1]);
            $url = s3Url($path);

            return '<img src="'.htmlspecialchars((string) $url, ENT_QUOTES)
                .'" data-path="'.htmlspecialchars($path, ENT_QUOTES).'">';
        }, $html) ?? $html;
    }

    /**
     * Deep-check body + answer_key coherence for the given type. Throws a
     * ValidationException naming the offending field.
     */
    public static function assertCoherent(QuestionType $type, array $body, ?array $key): void
    {
        $fail = function (string $field, string $message): never {
            throw ValidationException::withMessages([$field => [$message]]);
        };

        // Attachments ride on every type: uploaded files (path) or links.
        $attachments = $body['attachments'] ?? [];
        if (! is_array($attachments) || count($attachments) > 8) {
            $fail('body.attachments', 'A question can carry at most eight attachments.');
        }
        foreach ($attachments as $file) {
            $kind = is_array($file) ? ($file['kind'] ?? null) : null;
            if (! in_array($kind, self::ATTACHMENT_KINDS, true)) {
                $fail('body.attachments', 'Unknown attachment kind.');
            }
            if ($kind === 'file' && trim((string) ($file['path'] ?? '')) === '') {
                $fail('body.attachments', 'Uploaded attachments must reference their stored file.');
            }
            if ($kind !== 'file' && ! preg_match('~^https?://~i', (string) ($file['url'] ?? ''))) {
                $fail('body.attachments', 'Links must be valid http(s) URLs.');
            }
        }

        $options = $body['options'] ?? null;
        $optionIds = is_array($options) ? array_map(fn ($o) => (string) ($o['id'] ?? ''), $options) : [];

        switch ($type) {
            case QuestionType::McqSingle:
            case QuestionType::McqMulti:
                if (! is_array($options) || count($options) < 2) {
                    $fail('body.options', 'Provide at least two options.');
                }
                foreach ($options as $option) {
                    if (! isset($option['id']) || trim((string) ($option['text'] ?? '')) === '') {
                        $fail('body.options', 'Every option needs an id and a text.');
                    }
                }
                if (count(array_unique($optionIds)) !== count($optionIds)) {
                    $fail('body.options', 'Option ids must be unique.');
                }

                if ($type === QuestionType::McqSingle) {
                    $correct = (string) ($key['correct'] ?? '');
                    if (! in_array($correct, $optionIds, true)) {
                        $fail('answer_key.correct', 'Pick the correct option.');
                    }
                } else {
                    $correct = $key['correct'] ?? null;
                    if (! is_array($correct) || $correct === []) {
                        $fail('answer_key.correct', 'Pick at least one correct option.');
                    }
                    foreach ($correct as $id) {
                        if (! in_array((string) $id, $optionIds, true)) {
                            $fail('answer_key.correct', 'Correct answers must be among the options.');
                        }
                    }
                }
                break;

            case QuestionType::TrueFalse:
                if (! isset($key['correct']) || ! is_bool($key['correct'])) {
                    $fail('answer_key.correct', 'Mark the statement true or false.');
                }
                break;

            case QuestionType::ShortAnswer:
                // `accepted` may be empty — the answer then queues for manual grading.
                if (isset($key['accepted']) && ! is_array($key['accepted'])) {
                    $fail('answer_key.accepted', 'Accepted answers must be a list.');
                }
                break;

            case QuestionType::Numeric:
                if (! isset($key['value']) || ! is_numeric($key['value'])) {
                    $fail('answer_key.value', 'Provide the correct numeric value.');
                }
                if (isset($key['tolerance']) && (! is_numeric($key['tolerance']) || (float) $key['tolerance'] < 0)) {
                    $fail('answer_key.tolerance', 'Tolerance must be zero or more.');
                }
                break;

            case QuestionType::FillBlank:
                $blanks = $key['blanks'] ?? null;
                if (! is_array($blanks) || $blanks === []) {
                    $fail('answer_key.blanks', 'Provide the accepted answers for each blank.');
                }
                foreach ($blanks as $accepted) {
                    if (! is_array($accepted) || $accepted === []) {
                        $fail('answer_key.blanks', 'Every blank needs at least one accepted answer.');
                    }
                }
                break;

            case QuestionType::Matching:
                $left = $body['left'] ?? null;
                $right = $body['right'] ?? null;
                $pairs = $key['pairs'] ?? null;
                if (! is_array($left) || count($left) < 2 || ! is_array($right) || count($right) < 2) {
                    $fail('body.left', 'Provide at least two items on each side.');
                }
                if (! is_array($pairs) || count($pairs) !== count($left)) {
                    $fail('answer_key.pairs', 'Match every left item to a right item.');
                }
                $rightIds = array_map(fn ($o) => (string) ($o['id'] ?? ''), $right);
                foreach ($pairs as $rightId) {
                    if (! in_array((string) $rightId, $rightIds, true)) {
                        $fail('answer_key.pairs', 'Matches must point at the right-side items.');
                    }
                }
                break;

            case QuestionType::Essay:
                // Free response — rubric text is optional (answer_key.rubric).
                break;

            case QuestionType::Group:
                // A container: the stem is the passage/introduction; answers
                // live on its sub-questions.
                if ($key !== null && $key !== []) {
                    $fail('answer_key', 'A question group has no answer key of its own.');
                }
                break;
        }
    }
}
