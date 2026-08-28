<div id="in-app-bell" class="fi-topbar-item relative" data-tour="notification-bell">
    <button type="button" class="relative p-2" onclick="window.toggleInAppBell && window.toggleInAppBell()">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
        <span id="in-app-bell-count" class="absolute -top-0.5 -right-0.5 hidden min-w-[1.1rem] rounded-full bg-red-600 px-1 text-[10px] text-white">0</span>
    </button>
    <div id="in-app-bell-panel" class="hidden absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto rounded-lg bg-white dark:bg-gray-900 shadow-lg border border-gray-200 dark:border-gray-700 z-50 p-2 text-sm"></div>
</div>
<script>
(function () {
    const pollMs = 60000;
    function loadBell() {
        fetch(@json(url('/notifications/in-app')), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                const count = document.getElementById('in-app-bell-count');
                if (!count) return;
                if (data.unread > 0) {
                    count.textContent = data.unread;
                    count.classList.remove('hidden');
                } else {
                    count.classList.add('hidden');
                }
                window.__inAppNotifications = data.notifications || [];
            }).catch(() => {});
    }
    window.toggleInAppBell = function () {
        const panel = document.getElementById('in-app-bell-panel');
        if (!panel) return;
        panel.classList.toggle('hidden');
        panel.innerHTML = (window.__inAppNotifications || []).map(n => (
            '<button type="button" class="block w-full text-left p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800" data-id="'+n.id+'"><div class="font-medium">'+n.title+'</div><div class="text-xs opacity-70">'+n.body+'</div></button>'
        )).join('') || '<div class="p-2 opacity-70">No notifications</div>';
        panel.querySelectorAll('button[data-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                fetch('/notifications/in-app/' + btn.dataset.id + '/read', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' }
                }).then(loadBell);
            });
        });
    };
    loadBell();
    setInterval(loadBell, pollMs);
})();
</script>
