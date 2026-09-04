<?php

namespace App\Logging;

use App\Services\Analytics\Analytics;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

/**
 * Forwards Laravel log records to the PostHog Logs product (channel
 * `posthog` in config/logging.php, part of the default stack; min level via
 * POSTHOG_LOG_LEVEL). Records ship through Analytics::log → the queued
 * OTLP request in SendAnalyticsEventJob. Exceptions ALSO reach PostHog
 * error tracking as structured $exception events via bootstrap/app.php —
 * the two products serve different views of the same failure.
 */
class PostHogLogHandler extends AbstractProcessingHandler
{
    /** Cap per process so a log flood can't flood the queue. */
    private const int MAX_PER_PROCESS = 100;

    private int $forwarded = 0;

    protected function write(LogRecord $record): void
    {
        if (! Analytics::enabled() || Analytics::$sending) {
            return;
        }

        if ($this->forwarded >= self::MAX_PER_PROCESS) {
            return;
        }
        $this->forwarded++;

        $attributes = ['log_channel' => $record->channel];

        foreach ($record->context as $key => $value) {
            if ($value instanceof Throwable) {
                $attributes['exception'] = $value::class.': '.Str::limit($value->getMessage(), 300);
            } elseif (is_scalar($value) || $value === null) {
                $attributes[$key] = is_string($value) ? Str::limit($value, 200) : $value;
            }
        }

        Analytics::log($record->level->getName(), $record->message, $attributes);
    }
}
