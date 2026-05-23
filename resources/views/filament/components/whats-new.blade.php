<div x-data="{
    discoveries: [],
    showModal: false,
    async check() {
        try {
            const response = await fetch('/api/product-tour/feature-discovery');
            const json = await response.json();
            this.discoveries = json.data || [];
            if (this.discoveries.length > 0) {
                this.showModal = true;
            }
        } catch (e) {}
    },
    dismiss(tourKey) {
        fetch('/api/product-tour/dismiss-hint', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tour_key: tourKey }),
        });
        this.discoveries = this.discoveries.filter(d => d.tour_key !== tourKey);
        if (this.discoveries.length === 0) this.showModal = false;
    },
    startTour(tourKey) {
        this.showModal = false;
        if (window.__tourManager) {
            window.__tourManager.startTour(tourKey);
        }
    },
}"
     x-init="check()"
     x-show="showModal"
     x-cloak
     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50"
     role="dialog"
     aria-modal="true"
     aria-labelledby="whats-new-title">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6"
         @click.outside="showModal = false">
        <div class="flex items-center justify-between mb-4">
            <h2 id="whats-new-title" class="text-xl font-bold text-gray-900 dark:text-white">
                What's New
            </h2>
            <button @click="showModal = false"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    aria-label="Close">
                <x-filament::icon name="heroicon-o-x-mark" class="w-5 h-5" />
            </button>
        </div>
        <div class="space-y-3">
            <template x-for="discovery in discoveries" :key="discovery.tour_key">
                <div class="p-4 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 mt-0.5">
                            <x-filament::icon name="heroicon-o-sparkles" class="w-5 h-5 text-primary-500" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-gray-900 dark:text-white" x-text="discovery.label"></h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1" x-text="discovery.description"></p>
                            <div class="flex items-center gap-2 mt-3">
                                <button @click="startTour(discovery.tour_key)"
                                        class="text-sm font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                                    Take the Tour
                                </button>
                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                <button @click="dismiss(discovery.tour_key)"
                                        class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                    Dismiss
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
