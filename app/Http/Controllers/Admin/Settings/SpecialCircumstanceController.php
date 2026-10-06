<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\SpecialCircumstance;
use Illuminate\Http\Request;

class SpecialCircumstanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $specialCircumstances = SpecialCircumstance::query()
            ->when($q, fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.special-circumstances._table', compact('specialCircumstances'));
        }

        return view('admin.settings.special-circumstances.index', compact('specialCircumstances', 'q'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $specialCircumstance = new SpecialCircumstance();
        return view('admin.settings.special-circumstances.create', compact('specialCircumstance'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:special_circumstances,name',
        ]);

        SpecialCircumstance::create($request->only('name'));

        return redirect()->route('admin.settings.special-circumstances.index')
            ->with('success', 'Đã tạo hoàn cảnh đặc biệt.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SpecialCircumstance $specialCircumstance)
    {
        return view('admin.settings.special-circumstances.edit', compact('specialCircumstance'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SpecialCircumstance $specialCircumstance)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:special_circumstances,name,' . $specialCircumstance->id,
        ]);

        $specialCircumstance->update($request->only('name'));

        return redirect()->route('admin.settings.special-circumstances.index')
            ->with('success', 'Đã cập nhật hoàn cảnh đặc biệt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SpecialCircumstance $specialCircumstance)
    {
        $specialCircumstance->delete();

        return redirect()->route('admin.settings.special-circumstances.index')
            ->with('success', 'Đã xoá hoàn cảnh đặc biệt.');
    }
}