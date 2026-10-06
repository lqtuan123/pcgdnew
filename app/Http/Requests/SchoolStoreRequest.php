<?php
// app/Http/Requests/SchoolStoreRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'  => ['required','string','max:100','unique:schools,code'],
            'name'  => ['required','string','max:255'],
            'school_type_id'       => ['nullable','exists:school_types,id'],
            'educational_level_id' => ['nullable','exists:educational_levels,id'],

            // chấp nhận 2 kiểu địa chỉ (ít nhất 1 trong 2)
            'ward_code'      => ['nullable','string','required_without:ward_code_new'],
            'district_code'  => ['nullable','string'], // không bắt buộc nếu chỉ lưu địa chỉ mới
            'ward_code_new'  => ['nullable','string','required_without:ward_code'],
            'is_dissolved' => ['nullable','boolean'],
            'is_conglap' => ['nullable','boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ward_code.required_without'     => 'Chọn phường/xã (cũ) hoặc cấp 3 (mới).',
            'ward_code_new.required_without' => 'Chọn cấp 3 (mới) hoặc phường/xã (cũ).',
        ];
    }
}
