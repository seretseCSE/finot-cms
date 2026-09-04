<?php

namespace App\Ai\Tools;

use App\Ai\AiContext;
use Laravel\Ai\Contracts\Tool;

/**
 * Base of every Temari AI tool. Tools are the ONLY way a model reaches
 * tenant data — each one re-derives its scope from the AiContext and
 * re-checks the kernel / guardian-link authority itself (deny-by-default,
 * exactly like a controller). Numbers in answers must come from tool output,
 * never model recall. Results are JSON strings, capped small: tools feed a
 * context window, not a data export.
 */
abstract class AiTool implements Tool
{
    public function __construct(protected readonly AiContext $context)
    {
    }

    /** Successful payload. */
    protected function ok(mixed $data): string
    {
        return json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE) ?: '{"ok":false}';
    }

    /**
     * Refusal the model can relay honestly. Never include the data that was
     * denied; the reason is safe to show the user.
     */
    protected function deny(string $reason): string
    {
        return json_encode(['ok' => false, 'reason' => $reason], JSON_UNESCAPED_UNICODE) ?: '{"ok":false}';
    }
}
