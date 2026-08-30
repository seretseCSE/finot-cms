<x-filament-panels::page>
    @php
        $tabs = $this->tabs();
        $active = $this->tab;
        $target = $this->currentTarget();
    @endphp

    <nav class="fi-tabs" role="tablist" aria-label="{{ $this->getTitle() }}">
        @foreach ($tabs as $tab)
            <button
                type="button"
                role="tab"
                wire:click="selectTab('{{ $tab['key'] }}')"
                wire:key="hub-tab-{{ $hub }}-{{ $tab['key'] }}"
                aria-selected="{{ $active === $tab['key'] ? 'true' : 'false' }}"
                @class([
                    'fi-tabs-item',
                    'fi-active' => $active === $tab['key'],
                ])
            >
                <span class="fi-tabs-item-label">{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </nav>

    <div class="fi-resource-tab-hub-panel mt-6" role="tabpanel" wire:key="hub-panel-{{ $hub }}-{{ $active }}">
        @if ($target && $this->currentTabIsResource())
            @livewire($this->embeddedTableClass(), ['resource' => $target], key('hub-table-'.$hub.'-'.$active))
        @elseif ($target)
            @livewire($target, ['embeddedInHub' => true], key('hub-page-'.$hub.'-'.$active))
        @endif
    </div>
</x-filament-panels::page>
