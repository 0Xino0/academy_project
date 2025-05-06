<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'class_id',
        'registration_date',
    ];

    public function class()
    {
        return $this->belongsTo(ClassModel::class,'class_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class,'student_id');
    }
}
