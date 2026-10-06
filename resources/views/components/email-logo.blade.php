@php
use App\Helpers\LogoHelper;
$emailLogo = LogoHelper::getActiveEmailLogo();
@endphp

@if($emailLogo)
    <img src="{{ $emailLogo['url'] }}" 
         alt="{{ $emailLogo['name'] ?? 'Logo' }}" 
         width="{{ $emailLogo['width'] }}" 
         height="{{ $emailLogo['height'] }}"
         style="display: block; max-width: 100%; height: auto;"
         {{ $attributes }}>
@else
    {{-- Fallback cho email đẹp --}}
    <div style="width: 300px; height: 90px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: 600; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
        <svg style="width: 24px; height: 24px; margin-right: 12px;" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
        </svg>
        {{ $attributes->get('fallback-text', 'COMPANY LOGO') }}
    </div>
@endif
