@php
use App\Helpers\LogoHelper;
$logo = LogoHelper::getActiveLogo();
@endphp

@if($logo)
    <img src="{{ $logo['url'] }}" 
         alt="{{ $logo['name'] ?? 'Logo' }}" 
         width="{{ $logo['width'] }}" 
         height="{{ $logo['height'] }}"
         class="{{ $attributes->get('class', 'h-8 w-auto') }}"
         {{ $attributes->except('class') }}>
@else
    {{-- Fallback logo đẹp --}}
    <div class="{{ $attributes->get('class', 'h-8 w-auto') }} flex items-center justify-center bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg px-3 py-1 text-white font-bold text-sm shadow-lg">
        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
        </svg>
        {{ $attributes->get('fallback-text', 'LOGO') }}
    </div>
@endif