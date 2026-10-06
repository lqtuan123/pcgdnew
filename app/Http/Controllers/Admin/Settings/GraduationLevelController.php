<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\GraduationLevel;
use Illuminate\Http\Request;

class GraduationLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $graduationLevels = GraduationLevel::query()
            ->when($q, fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.graduation-levels._table', compact('graduationLevels'));
        }

        return view('admin.settings.graduation-levels.index', compact('graduationLevels', 'q'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $graduationLevel = new GraduationLevel();
        return view('admin.settings.graduation-levels.create', compact('graduationLevel'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:graduation_levels,name',
        ]);

        GraduationLevel::create($request->only('name'));

        return redirect()->route('admin.settings.graduation-levels.index')
            ->with('success', 'Đã tạo bậc tốt nghiệp.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GraduationLevel $graduationLevel)
    {
        return view('admin.settings.graduation-levels.edit', compact('graduationLevel'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GraduationLevel $graduationLevel)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:graduation_levels,name,' . $graduationLevel->id,
        ]);

        $graduationLevel->update($request->only('name'));

        return redirect()->route('admin.settings.graduation-levels.index')
            ->with('success', 'Đã cập nhật bậc tốt nghiệp.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GraduationLevel $graduationLevel)
    {
        $graduationLevel->delete();

        return redirect()->route('admin.settings.graduation-levels.index')
            ->with('success', 'Đã xoá bậc tốt nghiệp.');
    }
}