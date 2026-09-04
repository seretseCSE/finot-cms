<?php

namespace App\Jobs;

use App\Models\GeneratedDocument;
use App\Services\Documents\DocumentService;
use App\Services\Pdf\PdfRenderer;
use App\Support\Qr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders one queued official document to PDF (Cloudflare Browser Rendering)
 * and stores it on R2. Retries transient failures; a final failure leaves the
 * row `failed` with the error, which the UI surfaces instead of hanging.
 */
class GenerateDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $documentId)
    {
    }

    public function handle(PdfRenderer $renderer): void
    {
        $document = GeneratedDocument::find($this->documentId);

        if ($document === null || $document->isReady() || $document->revoked_at !== null) {
            return;
        }

        $document->update(['status' => GeneratedDocument::STATUS_PROCESSING]);

        try {
            $builder = DocumentService::builder($document->type);
            $payload = $builder->payload($document->subject, $document->params ?? []);

            $html = view("documents.{$builder->view()}", [
                ...$payload,
                'qr' => Qr::svgDataUri($builder->qrTarget($document)),
                'documentModel' => $document,
            ])->render();

            $pdf = $renderer->render($html, $builder->landscape());

            $path = sprintf(
                'documents/%d/%s/%s.pdf',
                $document->school_id ?? 0,
                $document->type,
                $document->public_token,
            );
            // Explicit ContentType so signed inline URLs display the PDF in
            // the browser tab instead of falling back to a forced download.
            Storage::put($path, $pdf, ['ContentType' => 'application/pdf']);

            $document->update([
                'status' => GeneratedDocument::STATUS_READY,
                'disk_path' => $path,
                'size_bytes' => strlen($pdf),
                'error' => null,
            ]);

            try {
                $builder->onReady($document->refresh());
            } catch (Throwable $e) {
                // Notifications must never fail the document itself.
                Log::error('Document onReady hook failed', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (Throwable $e) {
            $document->update([
                'status' => GeneratedDocument::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 490),
            ]);

            throw $e;
        }
    }
}
