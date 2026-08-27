<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GeneratedDocument;
use App\Services\Documents\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The official-PDF lane. POST /documents answers "give me this document":
 * instantly when the stored PDF is still current, otherwise it queues a
 * render and the client polls GET /documents/{id} until ready (seconds).
 * Every document carries a QR that resolves to the public verify endpoint.
 */
class DocumentController extends Controller
{
    public function store(Request $request, DocumentService $documents): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DocumentService::TYPES))],
            'subject_id' => ['nullable', 'integer'],
            'params' => ['sometimes', 'array'],
        ]);

        $builder = DocumentService::builder($data['type']);

        $params = [];
        if ($builder->rules() !== []) {
            $params = $request->validate(
                collect($builder->rules())
                    ->mapWithKeys(fn ($rules, $key) => ["params.{$key}" => $rules])
                    ->all(),
            )['params'] ?? [];
        }

        $subject = $builder->resolveSubject($data['subject_id'] ?? null);
        abort_if(($data['subject_id'] ?? null) !== null && $subject === null, 404);
        abort_unless($builder->authorize($request->user(), $subject, $params), 403);

        $document = $documents->ensure($data['type'], $subject, $params, $request->user());

        // On the sync queue the render already ran — reflect its outcome.
        return response()->json(['data' => self::statusPayload($document->refresh())]);
    }

    /** Poll until ready — the UI shows "generating…" while queued/processing. */
    public function show(Request $request, GeneratedDocument $document): JsonResponse
    {
        $builder = DocumentService::builder($document->type);

        abort_unless(
            $builder->authorize($request->user(), $document->subject, $document->params ?? []),
            403,
        );

        return response()->json(['data' => self::statusPayload($document)]);
    }

    /** Kill the QR of a wrongly issued document; reissue creates a new one. */
    public function revoke(Request $request, GeneratedDocument $document): JsonResponse
    {
        $builder = DocumentService::builder($document->type);

        abort_unless(
            $builder->authorize($request->user(), $document->subject, $document->params ?? []),
            403,
        );

        $document->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Document revoked — its QR now reports it as invalid.']);
    }

    /**
     * UNAUTHENTICATED verify endpoint behind the QR: proves authenticity with
     * a minimal summary — never marks, never pay amounts beyond what the
     * paper itself shows.
     */
    public function verify(string $token): JsonResponse
    {
        // public_token is a Postgres uuid — a malformed probe must 404,
        // never bubble up as a database error.
        abort_unless(Str::isUuid($token), 404);

        $document = GeneratedDocument::query()
            ->where('public_token', $token)
            ->firstOrFail();

        $builder = DocumentService::builder($document->type);

        return response()->json(['data' => [
            'type' => $document->type,
            'status' => $document->revoked_at !== null ? 'revoked' : 'valid',
            'issued_on' => $document->created_at?->toDateString(),
            'summary' => $builder->verifySummary($document),
            'download_url' => $document->revoked_at === null && $builder->publiclyDownloadable()
                ? $document->downloadUrl()
                : null,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function statusPayload(GeneratedDocument $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'status' => $document->status,
            'public_token' => $document->public_token,
            'url' => $document->downloadUrl(),
            'view_url' => $document->viewUrl(),
            'error' => $document->status === GeneratedDocument::STATUS_FAILED ? $document->error : null,
        ];
    }
}
