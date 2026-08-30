<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $total = $this->total();
        $from = $total === 0 ? 0 : (($this->tablePage - 1) * $this->perPage) + 1;
        $to = min($total, $this->tablePage * $this->perPage);
        $levels = $this->source === 'laravel'
            ? ['ERROR' => 'Error', 'CRITICAL' => 'Critical', 'WARNING' => 'Warning']
            : ['ERROR' => 'Error', 'WARNING' => 'Warning', 'CRITICAL' => 'Critical', 'NOTICE' => 'Notice'];
    @endphp

    <div class="elv-wrap">
        <div class="elv-toolbar">
            <div class="elv-tabs" role="tablist">
                <button type="button" class="elv-tab {{ $this->source === 'recorded' ? 'is-active' : '' }}" wire:click="$set('source', 'recorded')">
                    Recorded errors
                </button>
                <button type="button" class="elv-tab {{ $this->source === 'laravel' ? 'is-active' : '' }}" wire:click="$set('source', 'laravel')">
                    Laravel log
                </button>
            </div>
            <div class="elv-field">
                <label for="elv-level">Level</label>
                <select id="elv-level" class="elv-select" wire:model.live="level">
                    <option value="">All levels</option>
                    @foreach ($levels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="elv-panel">
            <div class="elv-scroll">
                <table class="elv-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Level</th>
                            <th>Message</th>
                            <th>Context</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $log)
                            @php $tone = strtoupper((string) $log['level']); @endphp
                            <tr>
                                <td class="elv-when">{{ $log['timestamp'] }}</td>
                                <td>
                                    <span @class([
                                        'elv-chip',
                                        'is-danger' => in_array($tone, ['ERROR', 'CRITICAL'], true),
                                        'is-warning' => $tone === 'WARNING',
                                    ])>{{ $log['level'] }}</span>
                                </td>
                                <td class="elv-msg">{{ $log['message'] }}</td>
                                <td class="elv-ctx">{{ $log['context'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="elv-empty">
                                    @if ($this->source === 'laravel')
                                        No matching lines in laravel.log for the last two months.
                                    @else
                                        No recorded errors yet.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($total > $this->perPage)
                <div class="elv-pager">
                    <p>Showing {{ $from }}–{{ $to }} of {{ $total }}</p>
                    <div class="elv-pager__btns">
                        <button type="button" class="elv-ghost" wire:click="previousPage" @disabled($this->tablePage <= 1)>Previous</button>
                        <span>Page {{ $this->tablePage }} of {{ $this->lastPage() }}</span>
                        <button type="button" class="elv-ghost" wire:click="nextPage" @disabled($this->tablePage >= $this->lastPage())>Next</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
