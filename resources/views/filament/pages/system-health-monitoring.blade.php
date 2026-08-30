<x-filament-panels::page>
    @php
        $health = $this->getSystemHealthData();
        $storage = (float) ($health['storage_usage']['percentage'] ?? 0);
        $storageUsed = $health['storage_usage']['used'] ?? '—';
        $storageTotal = $health['storage_usage']['total'] ?? '—';
        $dbMs = (float) ($health['db_query_time'] ?? 0);
        $errorRate = (float) ($health['error_rate'] ?? 0);
        $failedLogins = (int) ($health['failed_logins'] ?? 0);
        $sessions = (int) ($health['active_sessions'] ?? 0);

        $storageTone = $storage >= 90 ? 'danger' : ($storage >= 80 ? 'warning' : 'ok');
        $dbTone = $dbMs >= 200 ? 'danger' : ($dbMs >= 50 ? 'warning' : 'ok');
        $errorTone = $errorRate >= 10 ? 'danger' : ($errorRate >= 5 ? 'warning' : 'ok');
        $loginTone = $failedLogins >= 10 ? 'warning' : 'ok';

        $alerts = [];
        if ($storageTone !== 'ok') {
            $alerts[] = ['tone' => $storageTone, 'title' => 'Storage is filling up', 'body' => "Disk is at {$storage}%. Clear old files or expand the volume."];
        }
        if ($errorTone !== 'ok') {
            $alerts[] = [
                'tone' => $errorTone,
                'title' => 'Error share is high',
                'body' => "{$errorRate}% of recent log lines are errors.",
                'url' => \App\Filament\Pages\ErrorLogViewer::getUrl(),
            ];
        }
        if ($dbTone !== 'ok') {
            $alerts[] = ['tone' => $dbTone, 'title' => 'Database is slow', 'body' => "A simple query took {$dbMs} ms. Check MySQL load."];
        }

        $overall = collect([$storageTone, $dbTone, $errorTone])->contains('danger')
            ? 'danger'
            : (collect([$storageTone, $dbTone, $errorTone, $loginTone])->contains('warning') ? 'warning' : 'ok');
    @endphp

    <div class="sh-wrap">
        <div class="sh-banner is-{{ $overall }}">
            <div>
                <p class="sh-banner__title">
                    @if ($overall === 'ok')
                        All checks look normal
                    @elseif ($overall === 'warning')
                        Some checks need attention
                    @else
                        Action needed
                    @endif
                </p>
                <p class="sh-banner__meta">{{ $sessions }} active {{ \Illuminate\Support\Str::plural('session', $sessions) }} · cached for 5 minutes</p>
            </div>
            <span class="sh-chip is-{{ $overall }}">{{ $overall === 'ok' ? 'Healthy' : ($overall === 'warning' ? 'Warning' : 'Critical') }}</span>
        </div>

        <div class="sh-grid">
            <div class="sh-card">
                <p class="sh-kicker">Storage</p>
                <p class="sh-value is-{{ $storageTone }}">{{ rtrim(rtrim(number_format($storage, 1), '0'), '.') }}%</p>
                <p class="sh-meta">{{ $storageUsed }} used of {{ $storageTotal }}</p>
                <div class="sh-bar is-{{ $storageTone }}"><span style="width: {{ min(100, $storage) }}%"></span></div>
            </div>
            <div class="sh-card">
                <p class="sh-kicker">Database ping</p>
                <p class="sh-value is-{{ $dbTone }}">{{ $dbMs }}<span class="sh-value__unit"> ms</span></p>
                <p class="sh-meta">Time for a simple query</p>
            </div>
            <div class="sh-card">
                <p class="sh-kicker">Error share</p>
                <p class="sh-value is-{{ $errorTone }}">{{ $errorRate }}%</p>
                <p class="sh-meta">Share of error lines in the app log</p>
            </div>
            <div class="sh-card">
                <p class="sh-kicker">Failed logins</p>
                <p class="sh-value is-{{ $loginTone }}">{{ $failedLogins }}</p>
                <p class="sh-meta">Current failed-attempt total on accounts</p>
            </div>
        </div>

        <div class="sh-panel">
            <div class="sh-panel__head">Resources</div>
            <div class="sh-rows">
                <div class="sh-row"><span>Memory</span><span>{{ $health['memory_usage'] }}</span></div>
                <div class="sh-row"><span>CPU load</span><span>{{ $health['cpu_usage'] }}</span></div>
                <div class="sh-row"><span>Uptime</span><span>{{ $health['uptime'] }}</span></div>
                <div class="sh-row"><span>Active sessions</span><span>{{ $sessions }}</span></div>
            </div>
        </div>

        @if (count($alerts))
            <div class="sh-alerts">
                @foreach ($alerts as $alert)
                    <div class="sh-alert is-{{ $alert['tone'] }}">
                        <h3>{{ $alert['title'] }}</h3>
                        <p>{{ $alert['body'] }}</p>
                        @if (! empty($alert['url']))
                            <p><a href="{{ $alert['url'] }}" style="color:#1A44F7;font-weight:650">Open Error Log Viewer</a></p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
