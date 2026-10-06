<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class HouseRelationship extends Model
{
    //
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function people()
    {
        return $this->hasMany(Person::class);
    }
    public function residences()
    {
        return $this->hasMany(Residence::class, 'house_relationship_id');
    }
    public function contacts()
    {
        return $this->hasMany(PersonContact::class, 'house_relationship_id');
    }
}
