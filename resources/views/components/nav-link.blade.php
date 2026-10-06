@props([
    'href' => '#',
    'active' => false,
])

@php
    $classes = 'sidebar-link';
    if ($active) $classes .= ' sidebar-link-active';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    @isset($icon)
        <span class="text-slate-500 dark:text-slate-400" aria-hidden="true">
            {{ $icon }} {{-- slot icon là HtmlString, sẽ render đúng SVG --}}
        </span>
    @endisset

    <span class="sidebar-label" x-show="!sidebarCollapsed" x-cloak>{{ $slot }}</span>
</a>
