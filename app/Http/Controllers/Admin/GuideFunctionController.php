<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuideCategory;
use App\Models\GuideFunction;
use Illuminate\Http\Request;

class GuideFunctionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($categoryId)
    {
        $category = GuideCategory::findOrFail($categoryId);
        $functions = $category->functions;
        return view('admin.settings.guides.functions.index', compact('category', 'functions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($categoryId)
    {
        $category = GuideCategory::findOrFail($categoryId);
        return view('admin.settings.guides.functions.create', compact('category'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $categoryId)
    {
        $request->validate([
            'name' => 'required|string|max:150'
        ]);

        GuideFunction::create([
            'category_id' => $categoryId,
            'name' => $request->name
        ]);

        return redirect()->route('admin.settings.guides.functions.index', $categoryId)
            ->with('success', 'Chức năng đã được tạo thành công.');
    }

    /**
     * Display the specified resource.
     */
    public function show($categoryId, string $id)
    {
        $category = GuideCategory::findOrFail($categoryId);
        $function = GuideFunction::with('contents')->findOrFail($id);
        return view('admin.settings.guides.functions.show', compact('category', 'function'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($categoryId, string $id)
    {
        $category = GuideCategory::findOrFail($categoryId);
        $function = GuideFunction::findOrFail($id);
        return view('admin.settings.guides.functions.edit', compact('category', 'function'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $categoryId, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:150'
        ]);

        $function = GuideFunction::findOrFail($id);
        $function->update($request->only('name'));

        return redirect()->route('admin.settings.guides.functions.index', $categoryId)
            ->with('success', 'Chức năng đã được cập nhật thành công.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($categoryId, string $id)
    {
        $function = GuideFunction::findOrFail($id);
        $function->delete();

        return redirect()->route('admin.settings.guides.functions.index', $categoryId)
            ->with('success', 'Chức năng đã được xóa thành công.');
    }
}
