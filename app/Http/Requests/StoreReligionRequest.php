<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReligionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'        => ['nullable','string','max:50','unique:religions,code'],
            'name'        => ['required','string','max:190','unique:religions,name'],
            'slug'        => ['nullable','string','max:190','unique:religions,slug'],
            'is_official' => ['required','boolean'],
            'note'        => ['nullable','string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên tôn giáo',
            'is_official' => 'Chính thức',
        ];
    }
}
