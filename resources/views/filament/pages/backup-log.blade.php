<div class="space-y-4">
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <h3 class="text-sm font-medium text-gray-800 mb-2">Backup Log</h3>
        <div class="bg-gray-900 text-gray-100 rounded p-3 font-mono text-sm">
            <pre>{{ $backup->log_message }}</pre>
        </div>
        <div class="mt-3 text-sm text-gray-600">
            <p><strong>Backup:</strong> {{ $backup->filename }}</p>
            <p><strong>Status:</strong> {{ ucfirst($backup->status) }}</p>
            @if($backup->completed_at)
                <p><strong>Completed:</strong> {{ $backup->completed_at->format('M j, Y H:i:s') }}</p>
            @endif
        </div>
    </div>
</div>
