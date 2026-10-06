@extends('layouts.front')
@section('title', 'Hướng dẫn sử dụng')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6" x-data="{ sidebarOpen: false }">
    {{-- Navigation --}}
    <div class="mb-6">
        <div class="flex items-center space-x-4 text-sm">
            <a href="{{ route('front.home') }}" class="text-gray-600 hover:text-gray-900">Trang chủ</a>
            <span class="text-gray-400">|</span>
            <span class="text-blue-600 font-medium">Hướng dẫn</span>
            <span class="text-gray-400">|</span>
            <a href="#" class="text-gray-600 hover:text-gray-900">Chia sẻ</a>
        </div>
    </div>

    <div class="flex gap-6">
        {{-- Sidebar Toggle Button (Mobile) --}}
        <button @click="sidebarOpen = !sidebarOpen" 
                class="fixed top-20 left-4 z-50 p-2 bg-white border border-gray-200 rounded-lg shadow-md lg:hidden">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>

        {{-- Sidebar Overlay (Mobile) --}}
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-gray-600 bg-opacity-75 lg:hidden"></div>

        {{-- Sidebar --}}
        <div class="w-80 bg-white border border-gray-200 rounded-lg p-4 transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 fixed inset-y-0 left-0 z-50 lg:relative"
             :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Hướng dẫn sử dụng</h2>
                <button @click="sidebarOpen = false" class="lg:hidden p-1 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            {{-- Navigation Menu --}}
            <nav class="space-y-2">
                @foreach($categories as $category)
                <div x-data="{ open: {{ $selectedCategory && $selectedCategory->id == $category->id ? 'true' : 'false' }} }">
                    <button @click="open = !open" 
                            class="w-full flex items-center justify-between px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                        <span>{{ $category->name }}</span>
                        <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div x-show="open" x-transition class="ml-4 space-y-1">
                        @foreach($category->functions as $function)
                        <a href="{{ route('front.guide.function', $function->id) }}" 
                           @click="sidebarOpen = false"
                           class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg {{ $selectedFunction && $selectedFunction->id == $function->id ? 'bg-blue-50 text-blue-700' : '' }}">
                            {{ $function->name }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </nav>
        </div>

        {{-- Main Content --}}
        <div class="flex-1">
            @yield('guide-content')
        </div>
    </div>
</div>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
@endsection
