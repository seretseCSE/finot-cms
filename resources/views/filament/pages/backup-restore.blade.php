<x-filament-panels::page>
    @php
        $backups = $this->getBackups();
        $lastAuto = $this->lastAutomaticAt();
        $nextAuto = $this->nextAutomaticAt();
    @endphp

    <style>
        .bk-page { display: flex; flex-direction: column; gap: 1.15rem; }
        .bk-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .bk-kpi {
            background: var(--color-background-primary);
            border: 0.5px solid var(--color-border-tertiary);
            border-radius: var(--border-radius-lg);
            padding: 1.1rem 1.25rem;
        }
        .bk-kpi-label {
            font-size: 11px; font-weight: 600; letter-spacing: 0.06em;
            text-transform: uppercase; color: var(--color-text-secondary);
        }
        .bk-kpi-value {
            margin-top: 6px; font-size: 18px; font-weight: 650;
            color: var(--color-text-primary); line-height: 1.25;
        }
        .bk-kpi-sub { margin-top: 4px; font-size: 12px; color: var(--color-text-secondary); }
        .bk-panel {
            background: var(--color-background-primary);
            border: 0.5px solid var(--color-border-tertiary);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
        }
        .bk-panel-head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
            padding: 1rem 1.25rem;
            border-bottom: 0.5px solid var(--color-border-tertiary);
            background: var(--color-background-secondary);
        }
        .bk-panel-title { font-size: 14px; font-weight: 600; color: var(--color-text-primary); }
        .bk-panel-desc { margin-top: 3px; font-size: 12px; color: var(--color-text-secondary); }
        .bk-table { width: 100%; border-collapse: collapse; }
        .bk-table th {
            padding: 10px 16px; text-align: left;
            font-size: 11px; font-weight: 600; letter-spacing: 0.05em;
            text-transform: uppercase; color: var(--color-text-secondary);
            border-bottom: 0.5px solid var(--color-border-tertiary);
        }
        .bk-table th.right, .bk-table td.right { text-align: right; }
        .bk-table td {
            padding: 14px 16px;
            border-bottom: 0.5px solid var(--color-border-tertiary);
            color: var(--color-text-primary); font-size: 13px;
            vertical-align: middle;
        }
        .bk-table tr:last-child td { border-bottom: none; }
        .bk-file { font-weight: 550; word-break: break-all; }
        .bk-chip {
            display: inline-flex; align-items: center;
            padding: 2px 8px; border-radius: 99px;
            font-size: 11px; font-weight: 600;
        }
        .bk-chip.is-manual { background: color-mix(in srgb, #185FA5 14%, transparent); color: #185FA5; }
        .bk-chip.is-auto { background: color-mix(in srgb, #1D9E75 14%, transparent); color: #1D9E75; }
        .bk-chip.is-other { background: var(--color-background-secondary); color: var(--color-text-secondary); }
        .bk-actions { display: flex; justify-content: flex-end; gap: 6px; }
        .bk-icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; border-radius: 8px;
            border: 0.5px solid var(--color-border-secondary);
            background: var(--color-background-primary);
            color: var(--color-text-secondary); cursor: pointer;
        }
        .bk-icon-btn:hover { background: var(--color-background-secondary); color: var(--color-text-primary); }
        .bk-icon-btn.is-restore:hover { color: #b45309; }
        .bk-icon-btn.is-danger:hover { color: #b42318; }
        .bk-empty { padding: 2.5rem 1.5rem; text-align: center; color: var(--color-text-secondary); }
        .bk-empty strong { display: block; margin-bottom: 4px; color: var(--color-text-primary); font-size: 14px; }
        .bk-note {
            border: 0.5px solid color-mix(in srgb, #D4AC0D 35%, var(--color-border-tertiary));
            background: color-mix(in srgb, #D4AC0D 8%, var(--color-background-primary));
            border-radius: var(--border-radius-lg);
            padding: 1rem 1.25rem;
            color: var(--color-text-primary); font-size: 13px;
        }
        .bk-note h3 { margin: 0 0 6px; font-size: 13px; font-weight: 650; }
        .bk-note ul { margin: 0; padding-left: 1.1rem; color: var(--color-text-secondary); }
        .bk-note li + li { margin-top: 3px; }
    </style>

    <div class="bk-page">
        <div class="bk-kpis">
            <div class="bk-kpi">
                <div class="bk-kpi-label">Stored backups</div>
                <div class="bk-kpi-value">{{ count($backups) }}</div>
                <div class="bk-kpi-sub">Newest 30 are kept</div>
            </div>
            <div class="bk-kpi">
                <div class="bk-kpi-label">Last automatic</div>
                <div class="bk-kpi-value">{{ $lastAuto ?? 'None yet' }}</div>
                <div class="bk-kpi-sub">Created by the nightly job</div>
            </div>
            <div class="bk-kpi">
                <div class="bk-kpi-label">Next automatic</div>
                <div class="bk-kpi-value">{{ $nextAuto }}</div>
                <div class="bk-kpi-sub">Requires the scheduler to be running</div>
            </div>
            <div class="bk-kpi">
                <div class="bk-kpi-label">What is saved</div>
                <div class="bk-kpi-value">Full system</div>
                <div class="bk-kpi-sub">Database, files, and config</div>
            </div>
        </div>

        <div class="bk-panel">
            <div class="bk-panel-head">
                <div>
                    <div class="bk-panel-title">Available backups</div>
                    <div class="bk-panel-desc">Download a copy off this server. Restore replaces all current data.</div>
                </div>
            </div>

            @if (count($backups) === 0)
                <div class="bk-empty">
                    <strong>No backups yet</strong>
                    Use Create Backup, or wait for the first nightly run at 1:30 AM.
                </div>
            @else
                <div style="overflow-x:auto;">
                    <table class="bk-table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th class="right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($backups as $backup)
                                <tr>
                                    <td class="bk-file">{{ $backup['filename'] }}</td>
                                    <td>
                                        <span @class([
                                            'bk-chip',
                                            'is-manual' => $backup['type'] === 'Manual',
                                            'is-auto' => $backup['type'] === 'Automatic',
                                            'is-other' => ! in_array($backup['type'], ['Manual', 'Automatic'], true),
                                        ])>{{ $backup['type'] }}</span>
                                    </td>
                                    <td>{{ $backup['size'] }}</td>
                                    <td>{{ $backup['created_at'] }}</td>
                                    <td class="right">
                                        <div class="bk-actions">
                                            <button type="button" class="bk-icon-btn" wire:click="downloadBackup('{{ $backup['filename'] }}')" title="Download">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            </button>
                                            <button type="button" class="bk-icon-btn is-restore" wire:click="restoreBackup('{{ $backup['filename'] }}')" wire:confirm="Restore will replace ALL current data and put the site in maintenance mode. Continue?" title="Restore">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            </button>
                                            <button type="button" class="bk-icon-btn is-danger" wire:click="deleteBackup('{{ $backup['filename'] }}')" wire:confirm="Delete this backup file?" title="Delete">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="bk-note">
            <h3>Before you restore</h3>
            <ul>
                <li>Restore overwrites members, payments, users, files, and current settings.</li>
                <li>The site goes into maintenance until the restore finishes.</li>
                <li>Create a backup first if you might need today’s data again.</li>
                <li>Keep a downloaded copy somewhere other than this computer.</li>
            </ul>
        </div>
    </div>
</x-filament-panels::page>
