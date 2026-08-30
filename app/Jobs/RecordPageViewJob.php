<?php

namespace App\Jobs;

use App\Models\PageView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordPageViewJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $path,
        public readonly ?string $referrer,
        public readonly string $sessionHash,
        public readonly string $recordedAt,
    ) {
    }

    public function handle(): void
    {
        PageView::query()->create([
            'path' => $this->path,
            'referrer' => $this->referrer,
            'session_hash' => $this->sessionHash,
            'created_at' => $this->recordedAt,
        ]);
    }
}
