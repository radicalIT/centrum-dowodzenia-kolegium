<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $guarded = [];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
    
    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class);
    }
    public function cohorts()
    {
        return $this->belongsToMany(Cohort::class);
    }
    public function exams()
    {
        return $this->hasMany(Exam::class);
    }
}
