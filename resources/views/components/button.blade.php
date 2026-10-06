@props([
    'variant' => 'primary', // primary | secondary | danger
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center gap-2 rounded-xl px-4 py-2 font-medium focus:outline-none focus:ring-2 shadow-sm disabled:opacity-60 disabled:cursor-not-allowed';
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-700 focus:ring-primary/30',
        'secondary' => 'bg-slate-200 text-slate-800 hover:bg-slate-300 focus:ring-slate-300',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-300',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>
