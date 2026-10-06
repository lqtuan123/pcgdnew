<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'        => ['nullable','string','max:100','unique:documents,code'],
            'title'       => ['required','string','max:255'],
            'issued_date' => ['nullable','date'],
            'issuer_id'   => ['nullable','integer','exists:issuers,id'],
            'status'      => ['required','in:draft,published,archived'],
            'file'        => ['nullable','file','mimes:pdf,doc,docx,xls,xlsx,ppt,pptx','max:20480'], // 20MB
        ];
    }

    public function attributes(): array
    {
        return [
            'code'  => 'Số/Ký hiệu',
            'title' => 'Tiêu đề',
            'issued_date' => 'Ngày ban hành',
            'issuer_id' => 'Đơn vị cấp',
            'file'  => 'Tệp văn bản',
            'status'=> 'Trạng thái',
        ];
    }
}
