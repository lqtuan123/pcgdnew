<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Logo;
use App\Models\LogoSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class LogoController extends Controller
{
    public function index()
    {
        $logos = Logo::with('sizes')->orderBy('type')->orderBy('created_at', 'desc')->get();
        return view('admin.settings.logos.index', compact('logos'));
    }

    public function create()
    {
        return view('admin.settings.logos.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:png,jpg,jpeg,svg,ico|max:2048',
            'type' => 'required|in:logo,favicon,email',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        // Lưu file gốc
        $path = $request->file('file')->store('logos/original', 'public');

        $logo = Logo::create([
            'type' => $request->type,
            'name' => $request->name ?: $request->file('file')->getClientOriginalName(),
            'description' => $request->description,
            'original_path' => $path,
            'is_active' => false,
        ]);

        // Tạo các kích thước khác nhau tùy theo loại
        $sizes = $this->getSizesForType($request->type);
        
        $manager = new ImageManager(new Driver());
        
        foreach ($sizes as [$w, $h]) {
            $image = $manager->read($request->file('file'));
            
            // Tính toán kích thước mới giữ tỉ lệ
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            
            $ratio = min($w / $originalWidth, $h / $originalHeight);
            $newWidth = (int) ($originalWidth * $ratio);
            $newHeight = (int) ($originalHeight * $ratio);
            
            $image = $image->resize($newWidth, $newHeight);

            $resizedPath = "logos/{$logo->id}/{$w}x{$h}.png";
            Storage::disk('public')->put($resizedPath, $image->toPng());

            LogoSize::create([
                'logo_id' => $logo->id,
                'width' => $w,
                'height' => $h,
                'path' => $resizedPath,
                'is_active' => false,
            ]);
        }

        return redirect()->route('admin.settings.logos.index')
            ->with('success', ucfirst($request->type) . ' đã được tải lên thành công.');
    }

    public function show(Logo $logo)
    {
        $logo->load('sizes');
        return view('admin.settings.logos.show', compact('logo'));
    }

    public function activate(Logo $logo)
    {
        // Tắt tất cả logo cùng loại
        Logo::where('type', $logo->type)->update(['is_active' => false]);
        
        // Tắt tất cả size của tất cả logo cùng loại
        LogoSize::whereHas('logo', function($query) use ($logo) {
            $query->where('type', $logo->type);
        })->update(['is_active' => false]);
        
        // Kích hoạt logo này
        $logo->update(['is_active' => true]);
        
        // Tự động kích hoạt size đầu tiên của logo này
        $firstSize = $logo->sizes()->first();
        if ($firstSize) {
            $firstSize->update(['is_active' => true]);
        }

        return back()->with('success', 'Đã kích hoạt ' . ucfirst($logo->type) . ' thành công.');
    }

    public function activateSize(Logo $logo, LogoSize $size)
    {
        // Tắt tất cả logo cùng loại
        Logo::where('type', $logo->type)->update(['is_active' => false]);
        
        // Tắt tất cả size của tất cả logo cùng loại
        LogoSize::whereHas('logo', function($query) use ($logo) {
            $query->where('type', $logo->type);
        })->update(['is_active' => false]);
        
        // Kích hoạt logo chứa size này
        $logo->update(['is_active' => true]);
        
        // Kích hoạt size này
        $size->update(['is_active' => true]);

        return back()->with('success', 'Đã kích hoạt kích thước ' . $size->width . 'x' . $size->height . ' thành công.');
    }

    public function destroy(Logo $logo)
    {
        try {
            // Chọn disk storage dựa trên environment
            $disk = 'public';

            // Xóa file gốc
            if (!empty($logo->original_path) && Storage::disk($disk)->exists($logo->original_path)) {
                Storage::disk($disk)->delete($logo->original_path);
            }

            // Xóa tất cả các size
            if ($logo->sizes && $logo->sizes->count() > 0) {
                foreach ($logo->sizes as $size) {
                    if (!empty($size->path) && Storage::disk($disk)->exists($size->path)) {
                        Storage::disk($disk)->delete($size->path);
                    }
                    $size->delete(); // xóa record size trong DB luôn
                }
            }

            // Xóa thư mục logo (chỉ cho local storage)
            if ($disk === 'public') {
                $logoDir = "logos/{$logo->id}";
                if (Storage::disk($disk)->exists($logoDir)) {
                    Storage::disk($disk)->deleteDirectory($logoDir);
                }
            }

            // Xóa record logo
            $logo->delete();

            return back()->with('success', 'Đã xóa ' . ucfirst($logo->type) . ' thành công.');
        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi khi xóa logo: ' . $e->getMessage());
        }
    }

    public function getActiveLogo(Request $request)
    {
        $type = $request->get('type', 'logo');
        $logo = Logo::where('type', $type)
            ->where('is_active', true)
            ->with(['sizes' => function($query) {
                $query->where('is_active', true);
            }])
            ->first();
            
        return response()->json($logo);
    }

    private function getSizesForType($type)
    {
        switch ($type) {
            case 'favicon':
                return [[16, 16], [32, 32], [48, 48], [64, 64], [128, 128], [192, 192]];
            case 'email':
                return [[200, 60], [300, 90], [400, 120]];
            case 'logo':
            default:
                return [[64, 64], [128, 128], [256, 256], [512, 512]];
        }
    }
}
