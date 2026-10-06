@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'help' => null,
])

<div {{ $attributes->only('class')->class('space-y-1') }}>
    @if($label)
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $name }}">{{ $label }}</label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->except('class')->merge(['class' => 'block w-full rounded-xl border-slate-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100']) }}
    >

    @if($help)
        <p class="text-xs text-slate-500">{{ $help }}</p>
    @endif

    @error($name)
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
