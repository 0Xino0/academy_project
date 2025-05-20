<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'paid_amount',
        'total_amount',
        'is_paid',
    ];

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
