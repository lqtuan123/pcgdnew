@extends('layouts.admin')

@section('title', 'Backup ' . $backupId)
@section('crumb', 'Backup ' . $backupId)

@section('content')
  <div class="space-y-4">
    <div class="card">
      <h2 class="text-lg font-semibold">Thông tin</h2>
      <div class="mt-2 text-sm">
        <div><b>Mã backup:</b> {{ $manifest['backup_id'] ?? $backupId }}</div>
        <div><b>DB:</b> {{ $manifest['db'] ?? '' }}</div>
        <div><b>MySQL:</b> {{ $manifest['version'] ?? '' }}</div>
        <div><b>Kích thước part:</b> {{ isset($manifest['max_bytes']) ? round($manifest['max_bytes']/1024/1024,1) . ' MB' : '—' }}</div>
        <div class="mt-1">
          <a class="text-blue-600 hover:underline"
             href="{{ route('admin.backup.download', [$backupId, 'manifest.json']) }}">
            Tải manifest.json
          </a>
        </div>
      </div>
    </div>

    <div class="card">
      <h2 class="text-lg font-semibold">Danh sách file (theo thứ tự manifest)</h2>
      <div class="-mx-4 mt-2 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2 text-left">Bảng</th>
              <th class="px-4 py-2 text-left">Đường dẫn</th>
              <th class="px-4 py-2 text-left">Kích thước</th>
              <th class="px-4 py-2 text-right">Tải</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            @forelse($files as $f)
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-2">{{ $f['table'] }}</td>
                <td class="px-4 py-2"><code>{{ $f['path'] }}</code></td>
                <td class="px-4 py-2">
                  @php $kb = $f['size']/1024; $txt = $kb>=1024 ? number_format($kb/1024,1).' MB' : number_format($kb,1).' KB'; @endphp
                  {{ $txt }}
                </td>
                <td class="px-4 py-2 text-right">
                  <a class="text-blue-600 hover:underline"
                     href="{{ route('admin.backup.download', [$backupId, $f['path']]) }}">Tải</a>
                </td>
              </tr>
            @empty
              <tr><td class="px-4 py-3 text-slate-500" colspan="4">Không có file dữ liệu.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
