<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExportReady extends Notification
{
    use Queueable;

    public function __construct(
        protected string $filePath,
        protected string $filename,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Export Ready',
            'body' => "Your {$this->filename} export is ready for download.",
            'action' => [
                'label' => 'Download',
                'url' => route('exports.download', ['filename' => $this->filename]),
            ],
        ];
    }
}
