<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'season',
        'start_date',
        'end_date',
        'is_active'
    ];

    public function classes()
    {
        return $this->hasMany(ClassModel::class);
    }
}
