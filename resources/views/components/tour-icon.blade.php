@props([
    'name' => '',
    'class' => '',
    'size' => '24',
    'label' => '',
    'ariaHidden' => false,
])

@php
    $validNames = ['community', 'education', 'faith', 'events', 'leadership', 'giving'];
    $iconName = in_array($name, $validNames) ? $name : 'faith';
    $width = $size;
    $height = $size;
    $ariaAttr = $ariaHidden ? 'true' : ($label ? 'false' : 'true');
@endphp

<svg class="tour-icon {{ $class }}"
     width="{{ $width }}"
     height="{{ $height }}"
     viewBox="0 0 24 24"
     fill="none"
     stroke="currentColor"
     stroke-width="2"
     stroke-linecap="round"
     stroke-linejoin="round"
     aria-label="{{ $label }}"
     role="{{ $label ? 'img' : 'presentation' }}"
     @if($ariaAttr === 'true') aria-hidden="true" @endif
>
    <use href="#icon-{{ $iconName }}" />
</svg>
