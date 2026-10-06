<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportUsersRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return true; // quyền check ở Controller
    }
    
    public function rules(): array
    {
        return [
            'file' => ['required','file','mimes:xlsx,xls,csv'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file Excel để import.',
            'file.file' => 'File phải là file hợp lệ.',
            'file.mimes' => 'File phải có định dạng Excel (.xlsx, .xls) hoặc CSV.',
        ];
    }
}

