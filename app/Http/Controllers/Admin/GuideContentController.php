<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuideCategory;
use App\Models\GuideFunction;
use App\Models\GuideContent;
use Illuminate\Http\Request;

class GuideContentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($categoryId, $functionId)
    {
        $category = GuideCategory::findOrFail($categoryId);
        $function = GuideFunction::with('contents')->findOrFail($functionId);
        $contents = $function->contents;
        return view('admin.settings.guides.contents.index', compact('category', 'function', 'contents'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($categoryId, $functionId)
    {
        $category = GuideCategory::findOrFail($categoryId);
        $function = GuideFunction::findOrFail($functionId);
        return view('admin.settings.guides.contents.create', compact('category', 'function'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $categoryId, $functionId)
    {
        $request->validate([
            'title' => 'required|string|max:150',
            'content' => 'required|string',
            'video_url' => 'nullable|string|max:255'
        ], [
            'title.required' => 'Tiêu đề là bắt buộc',
            'title.max' => 'Tiêu đề không được vượt quá 150 ký tự',
            'content.required' => 'Nội dung là bắt buộc',
            'video_url.max' => 'Link video không được vượt quá 255 ký tự'
        ]);

        GuideContent::create([
            'function_id' => $functionId,
            'title' => $request->title,
            'content' => $request->content,
            'video_url' => $request->video_url
        ]);

        return redirect()->route('admin.settings.guides.functions.contents.index', [$categoryId, $functionId])
            ->with('success', 'Nội dung hướng dẫn đã được tạo thành công.');
    }

    /**
     * Display the specified resource.
     */
    public function show($categoryId, $functionId, string $id)
    {
        $category = GuideCategory::findOrFail($categoryId);
        $function = GuideFunction::findOrFail($functionId);
        $content = GuideContent::findOrFail($id);
        return view('admin.settings.guides.contents.show', compact('category', 'function', 'content'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($categoryId, $functionId, string $id)
    {
        $category = GuideCategory::findOrFail($categoryId);
        $function = GuideFunction::findOrFail($functionId);
        $content = GuideContent::findOrFail($id);
        return view('admin.settings.guides.contents.edit', compact('category', 'function', 'content'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $categoryId, $functionId, string $id)
    {
        $request->validate([
            'title' => 'required|string|max:150',
            'content' => 'required|string',
            'video_url' => 'nullable|string|max:255'
        ], [
            'title.required' => 'Tiêu đề là bắt buộc',
            'title.max' => 'Tiêu đề không được vượt quá 150 ký tự',
            'content.required' => 'Nội dung là bắt buộc',
            'video_url.max' => 'Link video không được vượt quá 255 ký tự'
        ]);

        $content = GuideContent::findOrFail($id);
        $content->update($request->only(['title', 'content', 'video_url']));

        return redirect()->route('admin.settings.guides.functions.contents.index', [$categoryId, $functionId])
            ->with('success', 'Nội dung hướng dẫn đã được cập nhật thành công.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($categoryId, $functionId, string $id)
    {
        $content = GuideContent::findOrFail($id);
        $content->delete();

        return redirect()->route('admin.settings.guides.functions.contents.index', [$categoryId, $functionId])
            ->with('success', 'Nội dung hướng dẫn đã được xóa thành công.');
    }
}
