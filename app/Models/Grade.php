<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'class_id',
        'grade',
    ];

    protected $casts = ['grade' => 'float'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id')->withTrashed();
    }
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id')->withTrashed();
    }
}
