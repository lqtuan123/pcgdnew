<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\EducationBlock;
use Illuminate\Http\Request;

class EducationBlockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $educationBlocks = EducationBlock::query()
            ->when($q, fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.education-blocks._table', compact('educationBlocks'));
        }

        return view('admin.settings.education-blocks.index', compact('educationBlocks', 'q'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $educationBlock = new EducationBlock();
        return view('admin.settings.education-blocks.create', compact('educationBlock'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:education_blocks,name',
        ]);

        EducationBlock::create($request->only('name'));

        return redirect()->route('admin.settings.education-blocks.index')
            ->with('success', 'Đã tạo khối học.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EducationBlock $educationBlock)
    {
        return view('admin.settings.education-blocks.edit', compact('educationBlock'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EducationBlock $educationBlock)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:education_blocks,name,' . $educationBlock->id,
        ]);

        $educationBlock->update($request->only('name'));

        return redirect()->route('admin.settings.education-blocks.index')
            ->with('success', 'Đã cập nhật khối học.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EducationBlock $educationBlock)
    {
        $educationBlock->delete();

        return redirect()->route('admin.settings.education-blocks.index')
            ->with('success', 'Đã xoá khối học.');
    }
}