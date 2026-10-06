<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\VocationalLevel;
use Illuminate\Http\Request;

class VocationalLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $vocationalLevels = VocationalLevel::query()
            ->when($q, fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.vocational-levels._table', compact('vocationalLevels'));
        }

        return view('admin.settings.vocational-levels.index', compact('vocationalLevels', 'q'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vocationalLevel = new VocationalLevel();
        return view('admin.settings.vocational-levels.create', compact('vocationalLevel'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:vocational_levels,name',
        ]);

        VocationalLevel::create($request->only('name'));

        return redirect()->route('admin.settings.vocational-levels.index')
            ->with('success', 'Đã tạo bậc tốt nghiệp nghề.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VocationalLevel $vocationalLevel)
    {
        return view('admin.settings.vocational-levels.edit', compact('vocationalLevel'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VocationalLevel $vocationalLevel)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:vocational_levels,name,' . $vocationalLevel->id,
        ]);

        $vocationalLevel->update($request->only('name'));

        return redirect()->route('admin.settings.vocational-levels.index')
            ->with('success', 'Đã cập nhật bậc tốt nghiệp nghề.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VocationalLevel $vocationalLevel)
    {
        $vocationalLevel->delete();

        return redirect()->route('admin.settings.vocational-levels.index')
            ->with('success', 'Đã xoá bậc tốt nghiệp nghề.');
    }
}