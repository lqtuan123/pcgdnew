<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'gender',
        'birth_date',
        'school_id',
        'contract_type_id', // hop dong lao dong, hop dong lam viec
        'receiving_benefits', // co the duoc huong bien che
        'identity_number', // CMND/CCCD
        'email',
        'phone_number',
        'ethnic_minority_id'
    ];

    // Quan hệ với trường
    public function school() {
        return $this->belongsTo(School::class);
    }

    // Quan hệ với dân tộc thiểu số
    public function ethnicMinority() {
        return $this->belongsTo(EthnicMinority::class);
    }

    // Quan hệ với loại hợp đồng
    public function contractType() {
        return $this->belongsTo(ContractType::class);
    }
}
