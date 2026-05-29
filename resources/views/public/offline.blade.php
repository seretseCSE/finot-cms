@extends('layouts.public')

@section('title', 'Offline - No Internet Connection')

@section('content')
<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div style="text-align: center; max-width: 400px;">
        <div style="margin-bottom: 20px;">
            <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-40);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </div>
        <h1 style="font-size: 2rem; margin-bottom: 16px; color: var(--text-display);">You're Offline</h1>
        <p style="color: var(--text-60); margin-bottom: 24px; line-height: 1.6;">
            It looks like you've lost your internet connection. Some features may not be available until you're back online.
        </p>
        <button onclick="window.location.reload()" class="btn btn-primary">
            Try Again
        </button>
        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border-subtle);">
            <p style="font-size: 0.875rem; color: var(--text-40);">
                <strong>Note:</strong> Cached content may still be available for browsing.
            </p>
        </div>
    </div>
</div>

<script>
// Auto-retry when online
window.addEventListener('online', () => {
    window.location.reload();
});
</script>
@endsection
