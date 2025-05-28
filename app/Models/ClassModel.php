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
        'startRegistration_date',
        'endRegistration_date',
        'term_id',
        'capacity',
        'tuition_fee',
        'name'

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
            ->withPivot('registration_date');
    }

    public function studentsWithGrades()
    {
        return $this->belongsToMany(Student::class, 'grades', 'class_id', 'student_id')
            ->withPivot('grade','entered_at');
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
        return $this->hasMany(Schedule::class);
    }
}

