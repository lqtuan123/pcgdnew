<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReligionRequest;
use App\Http\Requests\UpdateReligionRequest;
use App\Models\Religion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReligionController extends Controller
{
    public function index(Request $request)
    {
        $q   = trim((string)$request->input('q'));
        $off = $request->input('is_official'); // '0' | '1' | null

        $religions = Religion::query()
            ->when($q, fn($qr) => $qr->where(function($w) use($q){
                $w->where('name','like',"%{$q}%")
                  ->orWhere('code','like',"%{$q}%")
                  ->orWhere('slug','like',"%{$q}%");
            }))
            ->when($off !== null && $off !== '', fn($qr) => $qr->where('is_official', (int) $off))
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.religions._table', compact('religions'));
        }

        return view('admin.settings.religions.index', compact('religions','q','off'));
    }

    public function create()
    {
        $religion = new Religion(['is_official' => true]);
        return view('admin.settings.religions.create', compact('religion'));
    }

    public function store(StoreReligionRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        Religion::create($data);

        return redirect()->route('admin.settings.religions.index')
            ->with('success','Đã tạo tôn giáo.');
    }

    public function edit(Religion $religion)
    {
        return view('admin.settings.religions.edit', compact('religion'));
    }

    public function update(UpdateReligionRequest $request, Religion $religion)
    {
        $data = $request->validated();
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['name']);
        }
        $religion->update($data);

        return redirect()->route('admin.settings.religions.index')
            ->with('success','Đã cập nhật tôn giáo.');
    }

    public function destroy(Religion $religion)
    {
        $religion->delete();

        return redirect()->route('admin.settings.religions.index')
            ->with('success','Đã xoá tôn giáo.');
    }

    /**
     * Xóa hàng loạt tôn giáo
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:religions,id'
        ], [
            'ids.required' => 'Vui lòng chọn ít nhất một tôn giáo để xóa',
            'ids.array' => 'Dữ liệu không hợp lệ',
            'ids.min' => 'Vui lòng chọn ít nhất một tôn giáo để xóa'
        ]);

        $religions = Religion::whereIn('id', $request->ids)->get();
        $deletedCount = 0;
        $errorMessages = [];

        foreach ($religions as $religion) {
            try {
                $religion->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errorMessages[] = "Không thể xóa '{$religion->name}': " . $e->getMessage();
            }
        }

        if ($deletedCount > 0) {
            $message = "Đã xóa thành công {$deletedCount} tôn giáo";
            if (!empty($errorMessages)) {
                $message .= ". " . implode('; ', $errorMessages);
            }
            return redirect()->route('admin.settings.religions.index')
                ->with('success', $message);
        } else {
            return redirect()->route('admin.settings.religions.index')
                ->with('error', implode('; ', $errorMessages));
        }
    }
}
