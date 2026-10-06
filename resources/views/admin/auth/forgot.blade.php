<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quên mật khẩu · {{ config('app.name') }}</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="mb-6 flex items-center justify-center gap-3">
        <div class="h-10 w-10 rounded-2xl bg-white text-blue-600 ring-1 ring-blue-200 flex items-center justify-center shadow">
          <x-logo class="h-6 w-6" />
        </div>
        <div class="text-lg font-semibold">{{ config('app.name') }}</div>
      </div>

      <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <h1 class="text-lg font-semibold">Quên mật khẩu</h1>
        <p class="mt-1 text-sm text-slate-500">Nhập email để nhận liên kết đặt lại (mock).</p>

        <form class="mt-5 space-y-4" onsubmit="event.preventDefault(); alert('Đã gửi email (mock).');">
          <div>
            <label class="block text-sm font-medium" for="email">Email</label>
            <input id="email" type="email" required
                   class="mt-1 block w-full rounded-xl border-slate-300 focus:border-blue-600 focus:ring-blue-600" />
          </div>
          <button class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 font-medium text-white hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
            Gửi liên kết đặt lại
          </button>
        </form>

        <a href="{{ route('admin.login') }}" class="mt-4 inline-block text-sm text-blue-600 hover:underline">← Quay lại đăng nhập</a>
     
    
    </div>
    </div>
  </div>
</body>
</html>
