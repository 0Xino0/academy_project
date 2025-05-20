<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;


    protected $fillable = [
        'student_id',
        'class_id',
    ];

    public function class()
    {
        return $this->belongsTo(ClassModel::class,'class_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class,'student_id');
    }

    public function debt()
    {
        return $this->hasOne(Debt::class);
    }
}
