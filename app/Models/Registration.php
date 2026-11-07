<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Registration extends Model
{
    use HasFactory, SoftDeletes;

    public const DELETED_AT = 'cancelled_at';
    protected $hidden = ['active_slot'];


    protected $fillable = [
        'student_id',
        'class_id',
    ];

    public function class()
    {
        return $this->belongsTo(ClassModel::class,'class_id')->withTrashed();
    }

    public function student()
    {
        return $this->belongsTo(Student::class,'student_id')->withTrashed();
    }

    public function debt()
    {
        return $this->hasOne(Debt::class);
    }
}
