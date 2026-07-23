<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ScheduleEntry;
use App\Models\Subject;


class Semester extends Model
{
    protected $guarded = [];

    public function scheduleEntries()
    {
        return $this->hasMany(ScheduleEntry::class);
    }
    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}
