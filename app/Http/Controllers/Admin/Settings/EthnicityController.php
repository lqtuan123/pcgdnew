<?php
namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEthnicityRequest;
use App\Http\Requests\UpdateEthnicityRequest;
use App\Models\Ethnicity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


class EthnicityController extends Controller
{
    // public function index(Request $request)
    // {
    //     $q = trim((string)$request->input('q'));
    //     $min = $request->input('is_minority'); // '0' | '1' | null


    //     $ethnicities = Ethnicity::query()
    //                     ->when($q, fn($qr) => $qr->where(function($w) use($q){
    //                     $w->where('name','like',"%{$q}%")
    //                     ->orWhere('code','like',"%{$q}%");
    //                     }))
    //                     ->when($min !== null && $min !== '', fn($qr) => $qr->where('is_minority', (int) $min))
    //                     ->orderBy('name')
    //                     ->paginate(20);


    //     return view('admin.settings.ethnicities.index', compact('ethnicities','q','min'));
    // }
        public function index(Request $request)
        {
            $q = trim((string) $request->input('q'));
            $min = $request->input('is_minority'); // '0'|'1'|null


            $ethnicities = Ethnicity::query()
            ->when($q, fn($qr) => $qr->where(function($w) use ($q){
            $w->where('name','like',"%{$q}%")
            ->orWhere('code','like',"%{$q}%")
            ->orWhere('slug','like',"%{$q}%");
            }))
            ->when($min !== null && $min !== '', fn($qr) => $qr->where('is_minority', (int) $min))
            ->orderBy('name')
            ->paginate(20);


            if ($request->boolean('partial')) {
            return view('admin.settings.ethnicities._table', compact('ethnicities'));
        }


        return view('admin.settings.ethnicities.index', compact('ethnicities','q','min'));
        }

    public function create()
    {
        $ethnicity = new Ethnicity(['is_minority' => true]);
        return view('admin.settings.ethnicities.create', compact('ethnicity'));
    }


    public function store(StoreEthnicityRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        Ethnicity::create($data);
        return redirect()->route('admin.settings.ethnicities.index')
            ->with('success','Đã tạo dân tộc.');
    }


    public function edit(Ethnicity $ethnicity)
    {
        return view('admin.settings.ethnicities.edit', compact('ethnicity'));
    }


    public function update(UpdateEthnicityRequest $request, Ethnicity $ethnicity)
    {
        $data = $request->validated();
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['name']);
        }
        $ethnicity->update($data);
        return redirect()->route('admin.settings.ethnicities.index')
        ->with('success','Đã cập nhật dân tộc.');
    }


    public function destroy(Ethnicity $ethnicity)
    {
        $ethnicity->delete();
        return redirect()->route('admin.settings.ethnicities.index')
        ->with('success','Đã xoá dân tộc.');
    }

    /**
     * Xóa hàng loạt dân tộc
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:ethnicities,id'
        ], [
            'ids.required' => 'Vui lòng chọn ít nhất một dân tộc để xóa',
            'ids.array' => 'Dữ liệu không hợp lệ',
            'ids.min' => 'Vui lòng chọn ít nhất một dân tộc để xóa'
        ]);

        $ethnicities = Ethnicity::whereIn('id', $request->ids)->get();
        $deletedCount = 0;
        $errorMessages = [];

        foreach ($ethnicities as $ethnicity) {
            try {
                $ethnicity->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errorMessages[] = "Không thể xóa '{$ethnicity->name}': " . $e->getMessage();
            }
        }

        if ($deletedCount > 0) {
            $message = "Đã xóa thành công {$deletedCount} dân tộc";
            if (!empty($errorMessages)) {
                $message .= ". " . implode('; ', $errorMessages);
            }
            return redirect()->route('admin.settings.ethnicities.index')
                ->with('success', $message);
        } else {
            return redirect()->route('admin.settings.ethnicities.index')
                ->with('error', implode('; ', $errorMessages));
        }
    }
}