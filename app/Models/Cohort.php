<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
    protected $guarded = [];
    
    public function subjects()
    {
        return $this->belongsToMany(Subject::class);
    }
    public function scheduleEntries()
    {
        return $this->hasMany(ScheduleEntry::class);
    }
}
