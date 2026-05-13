<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ExportReady;
use App\Services\ExportAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected string $exportClass,
        protected array $columns,
        protected string $format,
        protected int $userId,
        protected ?array $ids = null,
        protected ?array $filters = null,
    ) {
    }

    public function handle(): void
    {
        $timestamp = now()->format('Y-m-d_His');
        $resourceType = $this->exportClass::resourceType();
        $filename = "{$resourceType}_{$timestamp}.{$this->format}";
        $diskPath = "exports/{$filename}";

        $writerType = match ($this->format) {
            'csv' => \Maatwebsite\Excel\Excel::CSV,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };

        $export = new $this->exportClass($this->columns, $this->ids, $this->filters);
        $recordCount = $export->collection()->count();

        Excel::store($export, $diskPath, 'local', $writerType);

        ExportAuditService::log(
            resourceType: $resourceType,
            format: $this->format,
            recordCount: $recordCount,
            filters: array_merge(
                $this->filters ?? [],
                $this->ids ? ['record_ids' => $this->ids] : [],
                ['columns' => $this->columns],
            ),
            filePath: $diskPath,
            exportedBy: $this->userId,
        );

        $user = User::find($this->userId);
        $user?->notify(new ExportReady($diskPath, $filename));
    }
}
