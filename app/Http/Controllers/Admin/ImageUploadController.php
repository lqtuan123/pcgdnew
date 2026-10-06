<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        // CKEditor 5 có thể gửi file với tên 'upload' hoặc tên khác
        $file = $request->file('upload') ?? $request->file('file');
        
        if (!$file) {
            return response()->json([
                'uploaded' => false,
                'error' => [
                    'message' => 'Không có file được upload'
                ]
            ], 400);
        }

        $request->validate([
            'upload' => 'required|image|mimes:png,jpg,jpeg,gif,webp|max:5120', // 5MB max
        ]);

        try {
            // Tạo tên file unique
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // Lưu file vào public/images/seeder-images để có thể backup cùng code
            $publicPath = public_path('images/seeder-images');
            if (!file_exists($publicPath)) {
                mkdir($publicPath, 0755, true);
            }
            
            // Resize ảnh trước khi lưu nếu quá lớn (tối đa 1200px width)
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file);
            
            if ($image->width() > 1200) {
                $image = $image->resize(1200, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                
                // Lưu ảnh đã resize
                $image->toJpeg(85)->save($publicPath . '/' . $filename);
            } else {
                // Lưu file gốc nếu không cần resize
                $file->move($publicPath, $filename);
            }
            
            $path = 'images/seeder-images/' . $filename;
            
            // Tạo URL để trả về với port đúng
            $baseUrl = request()->getSchemeAndHttpHost();
            $url = $baseUrl . '/' . $path;
            
            // Log để debug
            \Log::info('Image uploaded successfully', [
                'filename' => $filename,
                'path' => $path,
                'url' => $url
            ]);
            
            return response()->json([
                'uploaded' => true,
                'url' => $url
            ]);
            
        } catch (\Exception $e) {
            // Log chi tiết lỗi để debug
            \Log::error('Image upload failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'uploaded' => false,
                'error' => [
                    'message' => 'Lỗi khi upload ảnh: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}
