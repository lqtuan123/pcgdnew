<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonContact extends Model
{
    protected $fillable = [
        'person_id',
        'hoten',
        'house_relationship_id', // đổi thành 'relationshipid' nếu bạn dùng tên đó
        'dienthoai',
        'note',
    ];

    /* Quan hệ */
    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function relationship()
    {
        return $this->belongsTo(HouseRelationship::class, 'house_relationship_id');
    }

    /* Scopes tiện tra cứu */
    public function scopeNameLike($q, string $term)
    {
        $term = trim($term);
        if ($term === '') return $q;
        return $q->where('hoten', 'like', "%{$term}%");
    }

    public function scopePhone($q, string $phone)
    {
        $phone = trim($phone);
        if ($phone === '') return $q;
        return $q->where('dienthoai', $phone);
    }
}
