<footer class="mt-12 border-t">
  <div class="mx-auto max-w-7xl px-4 py-8 text-sm text-slate-600">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
      <div>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</div>
      <div class="flex items-center gap-4">
        <a href="{{ route('front.about') }}" class="hover:underline">Giới thiệu</a>
        <a href="#" class="hover:underline">Điều khoản</a>
      </div>
    </div>
  </div>
</footer>
