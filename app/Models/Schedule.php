<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Support\ScheduleTime;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'class_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function startTime(): Attribute
    {
        return Attribute::make(get: fn ($value) => ScheduleTime::normalize($value) ?? $value);
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(get: fn ($value) => ScheduleTime::normalize($value) ?? $value);
    }

    protected function dayOfWeek(): Attribute
    {
        return Attribute::make(get: fn ($value) => is_string($value) ? strtolower(trim($value)) : $value);
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class);
    }
}
