<header class="sticky top-0 z-40 border-b bg-white/80 backdrop-blur dark:bg-slate-900/70">
  <nav class="container mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <a href="{{ url('/') }}" class="flex items-center gap-2">
        <x-logo class="h-8 w-auto" />
        <span class="font-semibold tracking-tight text-slate-800 dark:text-slate-100">
          @php
            use App\Helpers\LogoHelper;
            $logo = LogoHelper::getActiveLogo();
          @endphp
          {{ $logo['name'] ?? config('app.name', 'MyApp') }}
        </span>
      </a>
      <span class="badge hidden sm:inline-flex">Blade + Tailwind</span>
    </div>

    <div class="flex items-center gap-3">
      <a href="{{ url('/') }}" class="text-sm hover:underline">Home</a>
      <a href="#" class="text-sm hover:underline">Docs</a>
      <button
        class="btn"
        x-data="{ on: false }"
        @click="document.documentElement.classList.toggle('dark'); on = !on"
        x-text="on ? 'Light' : 'Dark'">
      </button>
    </div>
  </nav>
</header>
