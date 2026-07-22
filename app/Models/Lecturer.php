<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Vinkla\Hashids\Facades\Hashids;

class Lecturer extends Model
{
    protected $guarded = [];
    protected $appends = ['encrypted_id'];
    
    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
    public function availabilities()
    {
        return $this->hasMany(Availability::class);
    }

    // Funkcja generująca wartość
    public function getEncryptedIdAttribute()
    {
        return Hashids::encode($this->id);
    }
}
