<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuideCategory;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = GuideCategory::with('functions')->get();
        return view('admin.settings.guides.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.settings.guides.categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'role_type' => 'required|in:admin,user'
        ]);

        GuideCategory::create($request->all());

        return redirect()->route('admin.settings.guides.index')
            ->with('success', 'Danh mục đã được tạo thành công.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = GuideCategory::with('functions')->findOrFail($id);
        return view('admin.settings.guides.categories.show', compact('category'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $category = GuideCategory::findOrFail($id);
        return view('admin.settings.guides.categories.edit', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'role_type' => 'required|in:admin,user'
        ]);

        $category = GuideCategory::findOrFail($id);
        $category->update($request->all());

        return redirect()->route('admin.settings.guides.index')
            ->with('success', 'Danh mục đã được cập nhật thành công.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = GuideCategory::findOrFail($id);
        $category->delete();

        return redirect()->route('admin.settings.guides.index')
            ->with('success', 'Danh mục đã được xóa thành công.');
    }
}
