<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportSchoolsRequest extends FormRequest
{
    public function authorize(): bool { return true; } // quyền check ở Service
    public function rules(): array
    {
        return [
            'file' => ['required','file','mimes:xlsx,xls,csv'],
        ];
    }
}
