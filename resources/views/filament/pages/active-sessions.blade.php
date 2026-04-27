<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Active Sessions Management</h2>
            <p class="text-gray-600 mb-6">Monitor and manage active user sessions. Force logout users or logout all users at once.</p>
            
            {{ $this->table }}
        </div>
    </div>

    <!-- Auto-refresh every 15 seconds -->
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ url, options }) => {
                // Auto-refresh every 15 seconds for active sessions
                if (url.includes('active-sessions')) {
                    setTimeout(() => {
                        @this.dispatch('refresh-sessions');
                    }, 15000);
                }
            });
        });

        // Listen for custom refresh event
        Livewire.on('refresh-sessions', () => {
            window.location.reload();
        });
    </script>
</x-filament-panels::page>
