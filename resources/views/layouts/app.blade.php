<!doctype html>
<html lang="vi" class="h-full" x-data="{ dark: false }" :class="dark ? 'dark' : ''">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <x-favicon />
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-slate-800 dark:bg-slate-900 dark:text-slate-100">
    @include('partials.navbar')

    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t bg-white/70 dark:bg-slate-800/60 backdrop-blur">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-slate-500 dark:text-slate-400">
            © {{ date('Y') }} {{ config('app.name') }} · Built with Laravel + Blade + Tailwind
        </div>
    </footer>
</body>
</html>
