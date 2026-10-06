<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\UserRole;

class NotSuperadminRole implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $role = UserRole::find($value);
        
        if ($role && $role->name === 'superadmin') {
            $fail('Vai trò siêu quản trị viên không được phép chọn.');
        }
    }
}