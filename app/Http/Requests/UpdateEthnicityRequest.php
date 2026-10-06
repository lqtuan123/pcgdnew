<?php
namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdateEthnicityRequest extends FormRequest
{
    public function authorize(): bool { return true; }


    public function rules(): array
    {
        $id = $this->route('ethnicity')?->id;
        return [
        'code' => ['nullable','string','max:50', Rule::unique('ethnicities','code')->ignore($id)],
        'name' => ['required','string','max:190', Rule::unique('ethnicities','name')->ignore($id)],
        'slug' => ['nullable','string','max:190', Rule::unique('ethnicities','slug')->ignore($id)],
        'is_minority' => ['required','boolean'],
        'note' => ['nullable','string'],
        ];
    }


    public function attributes(): array
    {
        return [
        'name' => 'Tên dân tộc',
        'is_minority' => 'Thuộc nhóm thiểu số',
        ];
    }
}