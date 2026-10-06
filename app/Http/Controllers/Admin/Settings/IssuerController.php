<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssuerRequest;
use App\Http\Requests\UpdateIssuerRequest;
use App\Models\Issuer;
use Illuminate\Http\Request;

class IssuerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $issuers = Issuer::query()
            ->when($q, fn($qr) => $qr->where(function($w) use ($q) {
                $w->where('name','like',"%{$q}%")
                  ->orWhere('code','like',"%{$q}%");
            }))
            ->withCount('documents')
            ->orderBy('name')
            ->paginate(20);

        if ($request->boolean('partial')) {
            return view('admin.settings.issuers._table', compact('issuers'));
        }

        return view('admin.settings.issuers.index', compact('issuers','q'));
    }

    public function create()
    {
        $issuer = new Issuer();
        return view('admin.settings.issuers.create', compact('issuer'));
    }

    public function store(StoreIssuerRequest $request)
    {
        Issuer::create($request->validated());

        return redirect()->route('admin.settings.issuers.index')
            ->with('success','Đã tạo đơn vị cấp văn bản.');
    }

    public function edit(Issuer $issuer)
    {
        return view('admin.settings.issuers.edit', compact('issuer'));
    }

    public function update(UpdateIssuerRequest $request, Issuer $issuer)
    {
        $issuer->update($request->validated());

        return redirect()->route('admin.settings.issuers.index')
            ->with('success','Đã cập nhật đơn vị cấp văn bản.');
    }

    public function destroy(Issuer $issuer)
    {
        $issuer->delete();

        return redirect()->route('admin.settings.issuers.index')
            ->with('success','Đã xoá đơn vị cấp văn bản.');
    }
}
