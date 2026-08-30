@php
    $url = \App\Filament\Pages\UserManual::getUrl();
    $active = request()->url() === $url;
@endphp
<div class="ft-user-manual">
    <a href="{{ $url }}"
       class="ft-user-manual__link{{ $active ? ' is-active' : '' }}"
       @if($active) aria-current="page" @endif>
        <x-filament::icon
            icon="heroicon-o-book-open"
            class="fi-icon fi-size-md ft-user-manual__icon"
        />
        <span class="ft-user-manual__label">User Manual</span>
    </a>
</div>
