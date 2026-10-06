<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\Issuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim((string) $request->input('q'));
        $issuer  = $request->input('issuer_id');
        $status  = $request->input('status');
        $from    = $request->input('from'); // yyyy-mm-dd
        $to      = $request->input('to');   // yyyy-mm-dd

        $documents = Document::query()
            ->with('issuer')
            ->when($q, fn($qr) => $qr->where(function($w) use ($q) {
                $w->where('title','like',"%{$q}%")
                  ->orWhere('code','like',"%{$q}%");
            }))
            ->when($issuer, fn($qr) => $qr->where('issuer_id', $issuer))
            ->when($status !== null && $status !== '', fn($qr) => $qr->where('status', $status))
            ->when($from, fn($qr) => $qr->whereDate('issued_date','>=',$from))
            ->when($to, fn($qr) => $qr->whereDate('issued_date','<=',$to))
            ->orderByDesc('issued_date')
            ->orderByDesc('id')
            ->paginate(20);

        $issuers = Issuer::orderBy('name')->get();

        if ($request->boolean('partial')) {
            return view('admin.documents._table', compact('documents'));
        }

        return view('admin.documents.index', compact('documents','issuers','q','issuer','status','from','to'));
    }

    public function create()
    {
        $document = new Document(['status' => 'published']);
        $issuers  = Issuer::orderBy('name')->get();
        return view('admin.documents.create', compact('document','issuers'));
    }

    public function store(StoreDocumentRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('documents', 'public');
        }

        $doc = Document::create($data);

        return redirect()->route('admin.documents.index')
            ->with('success','Đã tạo văn bản.');
    }

    public function show(Document $document)
    {
        $document->load('issuer');
        return view('admin.documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $issuers = Issuer::orderBy('name')->get();
        return view('admin.documents.edit', compact('document','issuers'));
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            // xoá file cũ nếu có
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            $data['file_path'] = $request->file('file')->store('documents', 'public');
        }

        $document->update($data);

        return redirect()->route('admin.documents.index')
            ->with('success','Đã cập nhật văn bản.');
    }

    public function destroy(Document $document)
    {
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('admin.documents.index')
            ->with('success','Đã xoá văn bản.');
    }

    public function download(Document $document)
    {
        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }
        return Storage::disk('public')->download($document->file_path, ($document->code ?: 'document').'.'.pathinfo($document->file_path, PATHINFO_EXTENSION));
    }
}
