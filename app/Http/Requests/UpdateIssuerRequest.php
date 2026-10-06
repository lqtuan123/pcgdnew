<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssuerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('issuer')?->id;

        return [
            'code' => ['nullable','string','max:50', Rule::unique('issuers','code')->ignore($id)],
            'name' => ['required','string','max:190', Rule::unique('issuers','name')->ignore($id)],
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
