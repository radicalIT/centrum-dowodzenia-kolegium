<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleEntry extends Model
{
    protected $guarded = [];

    // Ta relacja jest wymagana przez kontroler!
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class);
    }
}