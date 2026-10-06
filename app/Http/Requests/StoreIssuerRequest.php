<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIssuerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['nullable','string','max:50','unique:issuers,code'],
            'name' => ['required','string','max:190','unique:issuers,name'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên đơn vị cấp',
            'code' => 'Mã đơn vị',
        ];
    }
}
