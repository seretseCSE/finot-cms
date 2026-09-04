<x-filament-panels::page>

<style>
.wt-page { display: flex; flex-direction: column; gap: 1.25rem; padding-bottom: 2rem; }
.wt-filters { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.wt-chip {
    border: 1px solid color-mix(in srgb, var(--gray-400) 40%, transparent);
    background: var(--color-bg, transparent);
    color: inherit;
    border-radius: 999px;
    padding: 0.35rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
}
.wt-chip.is-active { background: rgb(var(--primary-600)); color: #fff; border-color: transparent; }
.wt-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; }
.wt-card {
    background: var(--gray-50);
    border: 1px solid color-mix(in srgb, var(--gray-400) 25%, transparent);
    border-radius: 0.75rem;
    padding: 1rem 1.1rem;
}
.dark .wt-card { background: color-mix(in srgb, var(--gray-900) 70%, transparent); }
.wt-label { font-size: 0.7rem; font-weight: 650; letter-spacing: .06em; text-transform: uppercase; opacity: .65; margin-bottom: 0.35rem; }
.wt-value { font-size: 1.45rem; font-weight: 700; line-height: 1.15; }
.wt-delta { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 600; margin-top: 0.35rem; }
.wt-delta.up { color: #16a34a; }
.wt-delta.down { color: #dc2626; }
.wt-title { font-size: 0.9rem; font-weight: 650; margin-bottom: 0.85rem; }
.wt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
.wt-bars { display: flex; align-items: flex-end; gap: 3px; height: 160px; overflow-x: auto; }
.wt-bar-col { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; min-width: 10px; flex: 1; height: 100%; }
.wt-bar { width: 100%; max-width: 18px; border-radius: 3px 3px 0 0; background: #3b82f6; min-height: 2px; }
.wt-bar.alt { background: #22c55e; }
.wt-bar-label { font-size: 9px; opacity: .55; margin-top: 4px; white-space: nowrap; }
.wt-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.45rem 0; border-bottom: 1px solid color-mix(in srgb, var(--gray-400) 20%, transparent); }
.wt-row:last-child { border-bottom: 0; }
.wt-path { font-size: 0.8rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wt-count { font-size: 0.8rem; font-weight: 650; opacity: .75; flex-shrink: 0; }
.wt-track { height: 4px; background: color-mix(in srgb, var(--gray-400) 25%, transparent); border-radius: 99px; overflow: hidden; margin-top: 4px; }
.wt-fill { height: 100%; background: #3b82f6; border-radius: 99px; }
.wt-empty { text-align: center; padding: 2rem 1rem; opacity: .55; font-size: 0.85rem; }
</style>

@php
    $data = $this->getReportData();
    $overview = $data['overview'];
    $deltas = $overview['deltas'];
    $hasHits = ($overview['pageviews'] ?? 0) > 0;
    $trendMax = max(1, ...(array_column($data['trend'], 'pageviews') ?: [1]));
    $hourMax = max(1, ...(array_column($data['by_hour'], 'pageviews') ?: [1]));
    $dowMax = max(1, ...(array_column($data['by_dow'], 'pageviews') ?: [1]));
    $channelMax = max(1, ...(array_column($data['channels'], 'views') ?: [1]));
    $sectionMax = max(1, ...(array_column($data['sections'], 'views') ?: [1]));

    $kpis = [
        ['label' => 'Pageviews', 'value' => number_format($overview['pageviews']), 'delta' => $deltas['pageviews'], 'invert' => false],
        ['label' => 'Unique visitors', 'value' => number_format($overview['unique']), 'delta' => $deltas['unique'], 'invert' => false],
        ['label' => 'Views / visit', 'value' => $overview['views_per_visit'], 'delta' => $deltas['views_per_visit'], 'invert' => false],
        ['label' => 'Bounce rate', 'value' => $overview['bounce_rate'].'%', 'delta' => $deltas['bounce_rate'], 'invert' => true],
        ['label' => 'New sessions', 'value' => number_format($overview['new_sessions']), 'delta' => $deltas['new_sessions'], 'invert' => false],
        ['label' => 'Returning', 'value' => number_format($overview['returning_sessions']), 'delta' => $deltas['returning_sessions'], 'invert' => false],
    ];
@endphp

<div class="wt-page">
    <div class="wt-filters">
        @foreach([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $value => $label)
            <button
                type="button"
                wire:click="$set('days', {{ $value }})"
                class="wt-chip {{ (int) $this->days === $value ? 'is-active' : '' }}"
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="wt-kpis">
        @foreach($kpis as $kpi)
            @php
                $delta = $kpi['delta'] ?? 0;
                $up = $delta >= 0;
                $good = $kpi['invert'] ? ! $up : $up;
            @endphp
            <div class="wt-card">
                <div class="wt-label">{{ $kpi['label'] }}</div>
                <div class="wt-value">{{ $kpi['value'] }}</div>
                <div class="wt-delta {{ $good ? 'up' : 'down' }}">
                    {{ $up ? '+' : '' }}{{ $delta }}% vs previous period
                </div>
            </div>
        @endforeach
    </div>

    @if(! $hasHits)
        <div class="wt-card wt-empty">No public page views in this range yet.</div>
    @else
        <div class="wt-card">
            <div class="wt-title">Pageviews vs unique visitors</div>
            <div class="wt-bars" role="img" aria-label="Daily pageviews and unique visitors">
                @foreach($data['trend'] as $point)
                    <div class="wt-bar-col" title="{{ $point['label'] }}: {{ $point['pageviews'] }} views, {{ $point['unique'] }} unique">
                        <div class="wt-bar" style="height: {{ max(2, ($point['pageviews'] / $trendMax) * 100) }}%;"></div>
                        @if($loop->index % max(1, (int) ceil(count($data['trend']) / 10)) === 0)
                            <div class="wt-bar-label">{{ $point['label'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="wt-grid">
            <div class="wt-card">
                <div class="wt-title">Traffic by hour</div>
                <div class="wt-bars" role="img" aria-label="Pageviews by hour of day">
                    @foreach($data['by_hour'] as $point)
                        <div class="wt-bar-col" title="{{ $point['label'] }}: {{ $point['pageviews'] }}">
                            <div class="wt-bar" style="height: {{ max(2, ($point['pageviews'] / $hourMax) * 100) }}%;"></div>
                            @if($point['hour'] % 3 === 0)
                                <div class="wt-bar-label">{{ $point['hour'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="wt-card">
                <div class="wt-title">Traffic by day of week</div>
                <div class="wt-bars" role="img" aria-label="Pageviews by day of week">
                    @foreach($data['by_dow'] as $point)
                        <div class="wt-bar-col" title="{{ $point['label'] }}: {{ $point['pageviews'] }}">
                            <div class="wt-bar alt" style="height: {{ max(2, ($point['pageviews'] / $dowMax) * 100) }}%;"></div>
                            <div class="wt-bar-label">{{ $point['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="wt-grid">
            <div class="wt-card">
                <div class="wt-title">Channels</div>
                @foreach($data['channels'] as $row)
                    <div class="wt-row" style="flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="display:flex; justify-content:space-between;">
                                <span class="wt-path">{{ $row['channel'] }}</span>
                                <span class="wt-count">{{ number_format($row['views']) }} · {{ $row['percent'] }}%</span>
                            </div>
                            <div class="wt-track"><div class="wt-fill" style="width: {{ ($row['views'] / $channelMax) * 100 }}%;"></div></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="wt-card">
                <div class="wt-title">By section</div>
                @forelse($data['sections'] as $row)
                    <div class="wt-row">
                        <span class="wt-path">{{ $row['section'] }}</span>
                        <span class="wt-count">{{ number_format($row['views']) }}</span>
                    </div>
                    <div class="wt-track"><div class="wt-fill" style="width: {{ ($row['views'] / $sectionMax) * 100 }}%;"></div></div>
                @empty
                    <div class="wt-empty">No section data</div>
                @endforelse
            </div>
        </div>

        <div class="wt-grid">
            <div class="wt-card">
                <div class="wt-title">Top pages</div>
                @forelse($data['top_pages'] as $row)
                    <div class="wt-row">
                        <span class="wt-path" title="{{ $row['path'] }}">{{ $row['path'] }}</span>
                        <span class="wt-count">{{ number_format($row['views']) }}</span>
                    </div>
                @empty
                    <div class="wt-empty">No pages</div>
                @endforelse
            </div>
            <div class="wt-card">
                <div class="wt-title">Landing pages</div>
                @forelse($data['landing_pages'] as $row)
                    <div class="wt-row">
                        <span class="wt-path" title="{{ $row['path'] }}">{{ $row['path'] }}</span>
                        <span class="wt-count">{{ number_format($row['views']) }}</span>
                    </div>
                @empty
                    <div class="wt-empty">No landings</div>
                @endforelse
            </div>
            <div class="wt-card">
                <div class="wt-title">Exit pages</div>
                @forelse($data['exit_pages'] as $row)
                    <div class="wt-row">
                        <span class="wt-path" title="{{ $row['path'] }}">{{ $row['path'] }}</span>
                        <span class="wt-count">{{ number_format($row['views']) }}</span>
                    </div>
                @empty
                    <div class="wt-empty">No exits</div>
                @endforelse
            </div>
            <div class="wt-card">
                <div class="wt-title">Referrers</div>
                @forelse($data['referrers'] as $row)
                    <div class="wt-row">
                        <span class="wt-path" title="{{ $row['host'] }}">{{ $row['host'] }}</span>
                        <span class="wt-count">{{ number_format($row['views']) }}</span>
                    </div>
                @empty
                    <div class="wt-empty">No external referrers</div>
                @endforelse
            </div>
        </div>
    @endif
</div>

</x-filament-panels::page>
