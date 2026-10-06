@php
use App\Helpers\LogoHelper;
$favicon = LogoHelper::getActiveFavicon();
$allFavicons = LogoHelper::getActiveFaviconAllSizes();
@endphp

@if($favicon)
    {{-- Primary favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ $favicon['url'] }}">
    
    {{-- Additional favicon sizes --}}
    @foreach($allFavicons as $size)
        <link rel="icon" 
              type="image/x-icon" 
              href="{{ $size['url'] }}"
              sizes="{{ $size['width'] }}x{{ $size['height'] }}">
    @endforeach
    
    {{-- Apple touch icon --}}
    @if(isset($allFavicons[2])) {{-- 192x192 size --}}
        <link rel="apple-touch-icon" href="{{ $allFavicons[2]['url'] }}">
    @endif
@else
    {{-- Fallback favicon đẹp --}}
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iNCIgZmlsbD0idXJsKCNncmFkaWVudCkiLz4KPHN2ZyB4PSI4IiB5PSI4IiB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIGZpbGw9IndoaXRlIj4KPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik00IDNhMiAyIDAgMCAwLTIgMnYxMGEyIDIgMCAwIDAgMiAyaDEyYTIgMiAwIDAgMCAyLTJWNWEyIDIgMCAwIDAtMi0ySDR6bTEyIDEySDRsNC04IDMgNiAyLTQgMyA2eiIvPgo8L3N2Zz4KPHN0b3Agb2Zmc2V0PSIwJSIgc3RvcC1jb2xvcj0iIzM2N0ZGRiIvPgo8c3RvcCBvZmZzZXQ9IjEwMCUiIHN0b3AtY29sb3I9IiM4QjVGRjAiLz4KPC9kZWZzPgo8L3N2Zz4K">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
@endif
