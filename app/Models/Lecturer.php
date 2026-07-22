<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lecturer extends Model
{
    protected $guarded = [];
    
    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
    public function availabilities()
    {
        return $this->hasMany(Availability::class);
    }
}
