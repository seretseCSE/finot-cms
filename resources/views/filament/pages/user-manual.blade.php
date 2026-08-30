<x-filament-panels::page>
    @php
        $tabs = $this->getTabs();
        $userRole = $this->getUserRole();
        $userRoles = $this->getUserDisplayRoles();
        $activeTab = in_array($userRole, array_keys($tabs), true) ? $userRole : array_key_first($tabs);
    @endphp

    <style>
        @media print {
            .um-no-print,
            .fi-sidebar,
            .fi-topbar,
            .fi-header-actions,
            nav[aria-label="Role guides"] {
                display: none !important;
            }

            .um-tab-panel,
            .um-tab-panel[x-cloak] {
                display: block !important;
                break-before: page;
            }

            .um-tab-panel:first-of-type {
                break-before: auto;
            }

            .fi-section {
                break-inside: avoid;
            }
        }
    </style>

    @if(!empty($userRoles))
        <div class="mb-4 px-1 um-no-print">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Your roles:
                @foreach($userRoles as $role)
                    <span class="inline-flex items-center rounded-md bg-primary-50 dark:bg-primary-900 px-2 py-1 text-xs font-medium text-primary-700 dark:text-primary-300 ring-1 ring-inset ring-primary-600/20 mr-1">
                        {{ $role['label'] }}
                    </span>
                @endforeach
            </p>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 um-no-print">
        <p class="text-sm text-gray-600 dark:text-gray-400 max-w-3xl">
            Start with <strong>How the system works</strong>, then open your role.
            Super Admin and Admin can open every role and print the full guide.
        </p>
        <x-filament::button
            type="button"
            color="gray"
            icon="heroicon-o-printer"
            x-on:click="window.print()"
        >
            Print this guide
        </x-filament::button>
    </div>

    @include('filament.pages.user-manual.partials.how-it-works')
    @include('filament.pages.user-manual.partials.shared-flows')

    @if(!empty($tabs))
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6" x-data="{ activeTab: '{{ $activeTab }}' }">
            <nav class="-mb-px flex flex-wrap gap-2 um-no-print" aria-label="Role guides">
                @foreach($tabs as $key => $tab)
                    <button
                        type="button"
                        x-on:click="activeTab = '{{ $key }}'"
                        :class="activeTab === '{{ $key }}'
                            ? 'border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'"
                        class="inline-flex items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium transition-colors rounded-t-lg"
                    >
                        <x-filament::icon icon="{{ $tab['icon'] }}" class="h-4 w-4" />
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </nav>

            <div class="mt-6 space-y-6">
                @foreach($tabs as $key => $tab)
                    <div
                        class="um-tab-panel"
                        x-show="activeTab === '{{ $key }}'"
                        x-cloak
                    >
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 print:block hidden">
                            {{ $tab['label'] }}
                        </h2>
                        @include('filament.pages.user-manual.partials.roles.'.$key)
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
