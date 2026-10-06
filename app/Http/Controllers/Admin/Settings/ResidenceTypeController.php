<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ResidenceType;
use Illuminate\Http\Request;

class ResidenceTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $residenceTypes = ResidenceType::query()
            ->when($q, fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.residence-types._table', compact('residenceTypes'));
        }

        return view('admin.settings.residence-types.index', compact('residenceTypes', 'q'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $residenceType = new ResidenceType();
        return view('admin.settings.residence-types.create', compact('residenceType'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:residence_types,name',
        ]);

        ResidenceType::create($request->only('name'));

        return redirect()->route('admin.settings.residence-types.index')
            ->with('success', 'Đã tạo diện cư trú.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ResidenceType $residenceType)
    {
        return view('admin.settings.residence-types.edit', compact('residenceType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ResidenceType $residenceType)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:residence_types,name,' . $residenceType->id,
        ]);

        $residenceType->update($request->only('name'));

        return redirect()->route('admin.settings.residence-types.index')
            ->with('success', 'Đã cập nhật diện cư trú.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ResidenceType $residenceType)
    {
        $residenceType->delete();

        return redirect()->route('admin.settings.residence-types.index')
            ->with('success', 'Đã xoá diện cư trú.');
    }
}