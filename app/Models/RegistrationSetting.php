<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationSetting extends Model
{
    protected $fillable = ['cancellation_requires_settlement'];
    protected $casts = ['cancellation_requires_settlement' => 'boolean'];
}
