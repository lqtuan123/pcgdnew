{{-- resources/views/components/guest-layout.blade.php --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ ($title ? $title.' — ' : '') . config('app.name', 'Laravel') }}</title>
  {{-- Nếu bạn đã dùng Vite --}}
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
  <main class="container mx-auto p-4">
    {{ $slot }}
  </main>
</body>
</html>
