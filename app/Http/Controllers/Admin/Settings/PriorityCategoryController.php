<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\PriorityCategory;
use Illuminate\Http\Request;

class PriorityCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $priorityCategories = PriorityCategory::query()
            ->when($q, fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.priority-categories._table', compact('priorityCategories'));
        }

        return view('admin.settings.priority-categories.index', compact('priorityCategories', 'q'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $priorityCategory = new PriorityCategory();
        return view('admin.settings.priority-categories.create', compact('priorityCategory'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:priority_categories,name',
        ]);

        PriorityCategory::create($request->only('name'));

        return redirect()->route('admin.settings.priority-categories.index')
            ->with('success', 'Đã tạo diện ưu tiên.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PriorityCategory $priorityCategory)
    {
        return view('admin.settings.priority-categories.edit', compact('priorityCategory'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PriorityCategory $priorityCategory)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:priority_categories,name,' . $priorityCategory->id,
        ]);

        $priorityCategory->update($request->only('name'));

        return redirect()->route('admin.settings.priority-categories.index')
            ->with('success', 'Đã cập nhật diện ưu tiên.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PriorityCategory $priorityCategory)
    {
        $priorityCategory->delete();

        return redirect()->route('admin.settings.priority-categories.index')
            ->with('success', 'Đã xoá diện ưu tiên.');
    }
}
