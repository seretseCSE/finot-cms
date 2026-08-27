<?php

namespace App\Jobs;

use App\Services\Analytics\Analytics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use PostHog\PostHog;
use Throwable;

/**
 * Ships one analytics payload (built by App\Services\Analytics\Analytics) to
 * PostHog off the request path. Deliberately quiet on failure: a dead
 * analytics endpoint must never flood the logs — which would themselves be
 * forwarded — or block the queue.
 */
class SendAnalyticsEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        if (! Analytics::enabled()) {
            return;
        }

        // A failure inside the SDK may log a warning; the guard keeps the
        // PostHog log handler from capturing it and re-queueing forever.
        Analytics::$sending = true;

        try {
            if ($this->payload['type'] === 'log') {
                $this->shipLogRecord();

                return;
            }

            PostHog::init(config('services.posthog.key'), [
                'host' => config('services.posthog.host'),
            ]);

            if ($this->payload['type'] === 'identify') {
                PostHog::identify([
                    'distinctId' => $this->payload['distinctId'],
                    'properties' => $this->payload['properties'],
                ]);
            } else {
                PostHog::capture(array_filter([
                    'distinctId' => $this->payload['distinctId'],
                    'event' => $this->payload['event'],
                    'properties' => $this->payload['properties'] ?? [],
                    'groups' => $this->payload['groups'] ?? null,
                ], fn ($v) => $v !== null));
            }

            PostHog::flush();
        } catch (Throwable) {
            $this->release($this->backoff()[min($this->attempts() - 1, 1)]);
        } finally {
            Analytics::$sending = false;
        }
    }

    /**
     * Log records go to the PostHog Logs product (OTLP over HTTP), not the
     * events pipeline — the /logs UI, severity filtering and retention all
     * live there.
     */
    private function shipLogRecord(): void
    {
        // Monolog names → OpenTelemetry severity numbers.
        $severity = match ($this->payload['level']) {
            'DEBUG' => 5,
            'INFO' => 9,
            'NOTICE' => 10,
            'WARNING' => 13,
            'ERROR' => 17,
            'CRITICAL' => 18,
            'ALERT' => 19,
            'EMERGENCY' => 21,
            default => 9,
        };

        $attributes = [];
        foreach ($this->payload['attributes'] as $key => $value) {
            $attributes[] = ['key' => $key, 'value' => self::otlpValue($value)];
        }

        Http::withToken(config('services.posthog.key'))
            ->acceptJson()
            ->post(rtrim(config('services.posthog.host'), '/').'/i/v1/logs', [
                'resourceLogs' => [[
                    'resource' => ['attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => 'temari-backend']],
                    ]],
                    'scopeLogs' => [[
                        'scope' => ['name' => 'laravel'],
                        'logRecords' => [[
                            'timeUnixNano' => $this->payload['time_unix_nano'],
                            'severityNumber' => $severity,
                            'severityText' => $this->payload['level'],
                            'body' => ['stringValue' => $this->payload['message']],
                            'attributes' => $attributes,
                        ]],
                    ]],
                ]],
            ])
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    private static function otlpValue(mixed $value): array
    {
        return match (true) {
            is_bool($value) => ['boolValue' => $value],
            is_int($value) => ['intValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            default => ['stringValue' => (string) $value],
        };
    }
}
