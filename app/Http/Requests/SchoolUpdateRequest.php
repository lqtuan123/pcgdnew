<?php
// app/Http/Requests/SchoolUpdateRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('school')?->id;

        return [
            'code'  => ['required','string','max:100', Rule::unique('schools','code')->ignore($id)],
            'name'  => ['required','string','max:255'],
            'school_type_id'       => ['nullable','exists:school_types,id'],
            'educational_level_id' => ['nullable','exists:educational_levels,id'],

            'ward_code'      => ['nullable','string','required_without:ward_code_new'],
            'district_code'  => ['nullable','string'],
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
