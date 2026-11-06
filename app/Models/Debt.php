<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Support\Money;

class Debt extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'amount',
        'remaining',
        'waived_amount',
    ];

    protected $casts = ['amount' => 'decimal:2', 'remaining' => 'decimal:2', 'waived_amount' => 'decimal:2'];
    protected $appends = ['total_amount', 'paid_amount', 'remaining_amount', 'is_paid', 'is_settled'];

    protected function totalAmount(): Attribute
    {
        return Attribute::make(get: fn () => $this->amount);
    }

    protected function remainingAmount(): Attribute
    {
        return Attribute::make(get: fn () => $this->remaining);
    }

    protected function paidAmount(): Attribute
    {
        return Attribute::make(get: fn () => Money::decimal(Money::cents($this->amount)
            - Money::cents($this->remaining) - Money::cents($this->waived_amount ?? '0.00')));
    }

    protected function isPaid(): Attribute
    {
        return Attribute::make(get: fn () => $this->paid_amount === $this->amount);
    }

    protected function isSettled(): Attribute
    {
        return Attribute::make(get: fn () => Money::cents($this->remaining) === 0);
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class)->withTrashed();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
