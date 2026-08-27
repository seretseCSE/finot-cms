<?php

namespace App\Services\Documents;

use App\Jobs\GenerateDocumentJob;
use App\Models\GeneratedDocument;
use App\Models\User;
use App\Services\Documents\Types\AnnualPlanDocument;
use App\Services\Documents\Types\DailyLessonPlanDocument;
use App\Services\Documents\Types\ExamPaperDocument;
use App\Services\Documents\Types\FinanceStatementDocument;
use App\Services\Documents\Types\PaymentReceiptDocument;
use App\Services\Documents\Types\PayslipDocument;
use App\Services\Documents\Types\ReportCardBatchDocument;
use App\Services\Documents\Types\ReportCardDocument;
use App\Services\Documents\Types\RosterDocument;
use App\Services\Documents\Types\TranscriptBatchDocument;
use App\Services\Documents\Types\TranscriptDocument;
use App\Services\Documents\Types\TransferLetterDocument;
use App\Services\Documents\Types\WithdrawalLetterDocument;
use App\Services\Documents\Types\YearReportCardBatchDocument;
use App\Services\Documents\Types\YearReportCardDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * The official-PDF pipeline: ensure() answers "give me this document" by
 * re-serving the stored PDF when the underlying data hasn't changed, or
 * queueing one render job when it has. Documents are pre-warmed at the
 * moment their source event happens (payment recorded, transfer approved,
 * results frozen), so by the time anyone clicks download it is already on R2.
 */
class DocumentService
{
    /** @var array<string, class-string<DocumentType>> */
    public const TYPES = [
        'payment_receipt' => PaymentReceiptDocument::class,
        'transfer_letter' => TransferLetterDocument::class,
        'withdrawal_letter' => WithdrawalLetterDocument::class,
        'transcript' => TranscriptDocument::class,
        'transcript_batch' => TranscriptBatchDocument::class,
        'report_card' => ReportCardDocument::class,
        'report_card_batch' => ReportCardBatchDocument::class,
        'year_report_card' => YearReportCardDocument::class,
        'year_report_card_batch' => YearReportCardBatchDocument::class,
        'roster' => RosterDocument::class,
        'finance_statement' => FinanceStatementDocument::class,
        'payslip' => PayslipDocument::class,
        'exam_paper' => ExamPaperDocument::class,
        'annual_plan' => AnnualPlanDocument::class,
        'daily_lesson_plan' => DailyLessonPlanDocument::class,
    ];

    public static function builder(string $type): DocumentType
    {
        $class = self::TYPES[$type] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown document type [{$type}].");
        }

        return app($class);
    }

    /**
     * Render the document for (type, subject, params) — always queues a fresh
     * render so a Print/Download click never re-serves a stale PDF. The
     * content hash is still recorded for reference, but never reused.
     *
     * @param  array<string, mixed>  $params
     */
    public function ensure(string $type, ?Model $subject, array $params = [], ?User $requestedBy = null): GeneratedDocument
    {
        $builder = self::builder($type);
        $payload = $builder->payload($subject, $params);
        $hash = $this->hash($builder, $params, $payload);

        $anchor = $builder->anchor($subject, $params);

        $document = GeneratedDocument::create([
            'school_id' => $anchor['school_id'],
            'branch_id' => $anchor['branch_id'],
            'type' => $type,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'params' => $params === [] ? null : $params,
            'version_hash' => $hash,
            'status' => GeneratedDocument::STATUS_QUEUED,
            'requested_by' => $requestedBy?->id,
        ]);

        GenerateDocumentJob::dispatch($document->id);

        return $document;
    }

    /**
     * Content hash: payload minus volatile keys. Same hash = same PDF.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $payload
     */
    private function hash(DocumentType $builder, array $params, array $payload): string
    {
        // Dot notation reaches volatile keys nested inside the payload
        // (e.g. transcript.generated_at) — anything left in breaks re-serving.
        Arr::forget($payload, $builder->volatileKeys());

        return hash('sha256', json_encode([$builder->templateVersion(), $params, $payload]));
    }
}
