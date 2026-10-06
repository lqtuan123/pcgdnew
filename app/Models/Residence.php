<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Residence extends Model
{
    protected $fillable = [
        'person_id',
        'head_person_id',
        'residence_type_id',
        'residence_status_id',
        'house_relationship_id',
    ];

    /* ========== Quan hệ ========== */

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function headPerson()
    {
        return $this->belongsTo(Person::class, 'head_person_id');
    }

    public function type()
    {
        return $this->belongsTo(ResidenceType::class, 'residence_type_id');
    }

    public function status()
    {
        return $this->belongsTo(ResidenceStatus::class, 'residence_status_id');
    }

    public function relationship()
    {
        return $this->belongsTo(HouseRelationship::class, 'house_relationship_id');
    }

    /* ========== Scopes tiện dụng ========== */

    public function scopeOfHead($q, int $headPersonId)
    {
        return $q->where('head_person_id', $headPersonId);
    }

    public function scopeOfType($q, int $typeId)
    {
        return $q->where('residence_type_id', $typeId);
    }

    public function scopeOfStatus($q, int $statusId)
    {
        return $q->where('residence_status_id', $statusId);
    }

    public function scopeOfRelationship($q, int $relationshipId)
    {
        return $q->where('house_relationship_id', $relationshipId);
    }
}
