<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    // protected $hidden = [
    //     'created_at',
    //     'updated_at',
    // ];

    protected $table = 'classes';

    protected $fillable = [
        'course_id',
        'teacher_id',
        'start_date',
        'end_date',
        'startRegistration_Date',
        'endRegistration_Date',
        'term_id',
        'capacity',
        'tuition_fee',
        'name'

    ];

    protected $casts = [
        'tuition_fee' => 'decimal:2',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function registeredStudents()
    {
        return $this->belongsToMany(Student::class, 'registrations', 'class_id', 'student_id')
            ->wherePivotNull('cancelled_at')
            ->withPivot('id')
            ->withTimestamps();
    }

    public function studentsWithGrades()
    {
        return $this->belongsToMany(Student::class, 'grades', 'class_id', 'student_id')
            ->withPivot('id', 'grade')
            ->withTimestamps();
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class,'class_id');
    }

    public function grades()
    {
        return $this->hasMany(Grade::class,'class_id');
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }
}

