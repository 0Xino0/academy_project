<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Teacher extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'salary',
        'join_date',
        'leave_date',
        'resume',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id' , 'id');
    }

    public function classes()
    {
        return $this->hasMany(ClassModel::class);
    }

    protected static function newFactory()
    {
        return TeacherFactory::new();
    }
}
