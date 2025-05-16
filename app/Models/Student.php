<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable=[
        'parent1_name',
        'parent1_phone',
        'parent2_name',
        'parent2_phone',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }

    public function registredClasses()
    {
        return $this->belongsToMany(ClassModel::class,'registrations','student_id','class_id')
            ->withPivot('registration_date');
    }

    public function classesWithGrades()
    {
        return $this->belongsToMany(ClassModel::class,'grades','student_id','class_id')
            ->withPivot('grade','entered_at');
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class,'student_id');
    }

    public function grades()
    {
        return $this->hasMany(Grade::class,'student_id');
    }

    protected static function newFactory()
    {
        return StudentFactory::new();
    }
}
