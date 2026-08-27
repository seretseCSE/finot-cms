<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTML → PDF through Cloudflare Browser Rendering's REST /pdf endpoint —
 * no headless Chrome ever runs on our own servers. Templates are fully
 * self-contained (inline CSS, no external requests), so renders stay fast
 * and deterministic. Requires CLOUDFLARE_ACCOUNT_ID + CLOUDFLARE_API_TOKEN
 * (token with the Browser Rendering permission).
 */
class PdfRenderer
{
    /** Returns the PDF binary for a self-contained HTML document. */
    public function render(string $html, bool $landscape = false): string
    {
        $accountId = config('services.cloudflare.account_id');
        $apiToken = config('services.cloudflare.api_token');

        if (! $accountId || ! $apiToken) {
            throw new RuntimeException(
                'PDF rendering is not configured — set CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_API_TOKEN.',
            );
        }

        $response = Http::withToken($apiToken)
            ->timeout(60)
            ->retry(2, 500, throw: false)
            ->post(
                "https://api.cloudflare.com/client/v4/accounts/{$accountId}/browser-rendering/pdf",
                [
                    'html' => $html,
                    // Wait for the (single) font stylesheet before printing.
                    'gotoOptions' => ['waitUntil' => 'networkidle0'],
                    'pdfOptions' => [
                        'format' => 'a4',
                        'landscape' => $landscape,
                        'printBackground' => true,
                        'preferCSSPageSize' => true,
                    ],
                ],
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                "PDF rendering failed ({$response->status()}): ".mb_substr($response->body(), 0, 300),
            );
        }

        return $response->body();
    }
}
