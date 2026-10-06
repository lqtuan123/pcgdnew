<?php
namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;


class StoreEthnicityRequest extends FormRequest
{
    public function authorize(): bool { return true; }


    public function rules(): array
    {
        return [
        'code' => ['nullable','string','max:50','unique:ethnicities,code'],
        'name' => ['required','string','max:190','unique:ethnicities,name'],
        'slug' => ['nullable','string','max:190','unique:ethnicities,slug'],
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