<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SurveyImportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'academic_year'     => ['required','regex:/^\d{4}-\d{4}$/'],
            'scheme'            => ['required','in:old,new'],
            'file'              => ['required','file','mimes:xlsx,xls,csv','max:20480'], // 20MB

            // mã địa bàn – tuỳ scheme
            'province_code'     => ['nullable','string','max:20'],
            'district_code'     => ['nullable','string','max:20'],
            'ward_code'         => ['nullable','string','max:20'],
            'province_code_new' => ['nullable','string','max:20'],
            'ward_code_new'     => ['nullable','string','max:20'],
        ];
    }

    public function withValidator($v)
    {
        $v->after(function ($v) {
            $data = $this->all();
            if (($data['scheme'] ?? null) === 'old') {
                if (empty($data['province_code'])) $v->errors()->add('province_code','Cần chọn Tỉnh (cũ).');
                if (empty($data['ward_code']) && empty($data['district_code'])) {
                    // cho phép chọn tới huyện hoặc xã; nếu muốn bắt buộc xã thì bật dòng dưới
                    // $v->errors()->add('ward_code','Cần chọn Phường/Xã (cũ).');
                }
            } else if (($data['scheme'] ?? null) === 'new') {
                if (empty($data['province_code_new'])) $v->errors()->add('province_code_new','Cần chọn Tỉnh (mới).');
                // xã mới có thể để trống nếu import cả tỉnh
            }
        });
    }
}
