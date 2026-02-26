<div
    x-data="{ open: false, available: false }"
    x-init="
        window.addEventListener('pwa:install-available', () => { available = true })
        window.addEventListener('pwa:show-install-prompt', () => { if (available) open = true })
        window.addEventListener('pwa:hide-install-prompt', () => { open = false })
    "
    x-show="open"
    x-transition
    class="fixed top-4 right-4 z-50 max-w-sm"
    style="display: none;"
>
    <div class="bg-blue-600 text-white p-4 rounded-lg shadow-lg">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="font-semibold">{{ __('Install App') }}</div>
                <div class="text-sm text-white/90">{{ __('Install this app for a better experience.') }}</div>
            </div>
            <button
                type="button"
                class="text-white/90 hover:text-white"
                @click="window.pwaTourManager?.dismissPwaPromptFor7Days(); open = false"
                aria-label="Close"
            >
                ×
            </button>
        </div>

        <div class="mt-3 flex gap-2">
            <button
                type="button"
                class="bg-white text-blue-700 px-3 py-1 rounded text-sm font-medium"
                @click="window.pwaTourManager?.installPWA()"
            >
                {{ __('Install') }}
            </button>
            <button
                type="button"
                class="border border-white/80 px-3 py-1 rounded text-sm"
                @click="window.pwaTourManager?.dismissPwaPromptFor7Days(); open = false"
            >
                {{ __('Not now') }}
            </button>
        </div>
    </div>
</div>
