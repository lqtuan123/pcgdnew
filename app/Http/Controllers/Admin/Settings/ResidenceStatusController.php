<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ResidenceStatus;
use Illuminate\Http\Request;

class ResidenceStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $residenceStatuses = ResidenceStatus::query()
            ->when($q, fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.residence-statuses._table', compact('residenceStatuses'));
        }

        return view('admin.settings.residence-statuses.index', compact('residenceStatuses', 'q'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $residenceStatus = new ResidenceStatus();
        return view('admin.settings.residence-statuses.create', compact('residenceStatus'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:residence_statuses,name',
        ]);

        ResidenceStatus::create($request->only('name'));

        return redirect()->route('admin.settings.residence-statuses.index')
            ->with('success', 'Đã tạo tình trạng cư trú.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ResidenceStatus $residenceStatus)
    {
        return view('admin.settings.residence-statuses.edit', compact('residenceStatus'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ResidenceStatus $residenceStatus)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:residence_statuses,name,' . $residenceStatus->id,
        ]);

        $residenceStatus->update($request->only('name'));

        return redirect()->route('admin.settings.residence-statuses.index')
            ->with('success', 'Đã cập nhật tình trạng cư trú.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ResidenceStatus $residenceStatus)
    {
        $residenceStatus->delete();

        return redirect()->route('admin.settings.residence-statuses.index')
            ->with('success', 'Đã xoá tình trạng cư trú.');
    }
}