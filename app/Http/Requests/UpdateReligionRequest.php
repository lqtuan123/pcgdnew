<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReligionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('religion')?->id;

        return [
            'code'        => ['nullable','string','max:50', Rule::unique('religions','code')->ignore($id)],
            'name'        => ['required','string','max:190', Rule::unique('religions','name')->ignore($id)],
            'slug'        => ['nullable','string','max:190', Rule::unique('religions','slug')->ignore($id)],
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
